<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Models\Annotation;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('resolve_marks')]
#[Description('Report progress on human marks while fixing them: set each mark to in_progress or resolved (with a short note on what you changed). When resolving, optionally attach after_image — a screenshot of the fixed area — so the human sees a before/after. Verifying stays the human\'s job — never claim a mark is done for them.')]
class ResolveMarksTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
        protected MarkLifecycleService $lifecycle,
    ) {}

    public function handle(Request $request): Response
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'marks' => 'required|array|min:1|max:50',
            'marks.*.id' => 'required|integer',
            'marks.*.status' => 'nullable|string|in:in_progress,resolved',
            'marks.*.note' => 'nullable|string|max:2000',
            'marks.*.after_image' => 'nullable|string',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        if ($review->effectiveStatus() !== Review::STATUS_CHANGES_REQUESTED) {
            return Response::error(match ($review->effectiveStatus()) {
                Review::STATUS_PENDING => 'The human has not decided yet — nothing to resolve. Poll get_review and follow next_action.',
                Review::STATUS_APPROVED => 'The human approved this review — there is nothing to resolve. Stop unless they ask for another checkup.',
                Review::STATUS_EXPIRED => 'This review expired. Start a fresh create_review if you still need a checkup.',
                default => 'You can only resolve marks after the human requests changes. Current status: '.$review->effectiveStatus().'.',
            });
        }

        try {
            ['updated' => $updated, 'skipped' => $skipped] = $this->lifecycle->applyAgentUpdates($review, $data['marks']);
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not apply those mark updates.');
        }

        if ($updated->isEmpty()) {
            return Response::error('No marks were updated: '.self::describeSkipped($skipped));
        }

        $resolved = $updated->where('status', Annotation::STATUS_RESOLVED)->count();
        $inProgress = $updated->where('status', Annotation::STATUS_IN_PROGRESS)->count();

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent.screenshots.annotations'])?->toAgentPayload();
        $payload['skipped_marks'] = $skipped;

        // Call out a partial batch — silently updating 3 of 5 reads as success.
        $warning = $skipped === [] ? '' : sprintf(
            "%d mark(s) were NOT updated — fix these before moving on: %s\n\n",
            count($skipped),
            self::describeSkipped($skipped),
        );

        return Response::text(
            "Updated {$updated->count()} mark(s): {$resolved} resolved, {$inProgress} in progress.\n\n".
            $warning.
            'Follow next_action. Once loop.outstanding_count is 0, open the next pass (create_review with parent_id) — '.
            "the human verifies your fixes there, so do not wait for verification on this pass.\n\n".
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @param  list<array{id: int, reason: string, detail: string}>  $skipped
     */
    protected static function describeSkipped(array $skipped): string
    {
        return collect($skipped)->map(fn (array $s) => "#{$s['id']} ({$s['reason']}: {$s['detail']})")->implode('; ');
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id')->required(),
            'marks' => $schema->array()
                ->items($schema->object())
                ->min(1)
                ->max(50)
                ->description('List of {id, status?, note?, after_image?}. id is the mark id from work_packets.pins[].id. status is "in_progress" or "resolved" (default resolved). note describes what you changed. after_image is an optional screenshot of the fixed area (https URL, data URL, or base64) shown to the human as a before/after.')
                ->required(),
        ];
    }
}
