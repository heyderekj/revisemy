<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Name('reply_to_marks')]
#[Title('Reply on marks')]
#[Description('Reply in a mark\'s thread: answer a question mark, explain what you changed or why you didn\'t, or ask before guessing. The human and any guests see the reply, labelled as your agent\'s. A reply never changes a mark — set progress with resolve_marks, and the human still verifies. Works while the review waits on the human or after they ask for changes.')]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ReplyToMarksTool extends Tool
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
            'replies' => 'required|array|min:1|max:20',
            'replies.*.id' => 'required|integer',
            'replies.*.body' => 'required|string|max:2000',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        // While it waits on the human, and after they ask for changes — the
        // moment an explanation is most useful.
        if (! in_array($review->effectiveStatus(), [Review::STATUS_PENDING, Review::STATUS_CHANGES_REQUESTED], true)) {
            return Response::error(match ($review->effectiveStatus()) {
                Review::STATUS_APPROVED => 'The human approved this review — the thread is closed. Stop unless they ask for another checkup.',
                Review::STATUS_EXPIRED => 'This review expired. Start a fresh create_review if you still need a checkup.',
                default => 'Replies only work while the review is open. Current status: '.$review->effectiveStatus().'.',
            });
        }

        ['replied' => $replied, 'skipped' => $skipped] = $this->lifecycle->replyAsAgent($review, $data['replies']);

        if ($replied->isEmpty()) {
            return Response::error('No replies were posted: '.self::describeSkipped($skipped));
        }

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings', 'parent.screenshots.annotations'])?->toAgentPayload();
        $payload['skipped_replies'] = $skipped;

        $warning = $skipped === [] ? '' : sprintf(
            "%d reply(ies) were NOT posted — fix these: %s\n\n",
            count($skipped),
            self::describeSkipped($skipped),
        );

        return Response::text(
            "Posted {$replied->count()} reply(ies). Replies don't change any mark — use resolve_marks for progress.\n\n".
            $warning.
            "Follow next_action.\n\n".
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
            'replies' => $schema->array()
                ->items($schema->object())
                ->min(1)
                ->max(20)
                ->description('List of {id, body}. id is the mark id from work_packets.pins[].id (not the M# number). body is your reply, up to 2000 characters, in plain words for the human.')
                ->required(),
        ];
    }
}
