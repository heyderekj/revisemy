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
            return Response::error('You can only resolve marks after the human requests changes. Current status: '.$review->effectiveStatus().'.');
        }

        try {
            $updated = $this->lifecycle->applyAgentUpdates($workspace, $data['marks']);
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not apply those mark updates.');
        }

        if ($updated->isEmpty()) {
            return Response::error('None of those mark ids belong to a review on this try token. Check work_packets.pins[].id.');
        }

        $resolved = $updated->where('status', Annotation::STATUS_RESOLVED)->count();
        $inProgress = $updated->where('status', Annotation::STATUS_IN_PROGRESS)->count();

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent.screenshots.annotations'])?->toAgentPayload();

        return Response::text(
            "Updated {$updated->count()} mark(s): {$resolved} resolved, {$inProgress} in progress.\n\n".
            'The human still has to verify resolved marks — keep polling get_review and follow next_action. '.
            "Open the next pass only once loop.outstanding_count is 0.\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
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
