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
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * The agent's side of a mark's conversation. A reply lands on the mark's
 * timeline under the Agent label and never moves the mark — status changes
 * stay with resolve_marks, and verify stays with the human.
 */
#[Name('reply_to_marks')]
#[Description('Answer a person on a mark\'s thread: a question they asked, a reopen with a comment, or a choice you need them to make. Each reply shows on the mark\'s timeline labelled Agent, on the board, the review page and inline. A reply never changes a mark\'s status — use resolve_marks for that (it also takes a reply). Read work_packets.pins[].timeline first; awaiting_reply is true where a person spoke last.')]
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

        $status = $review->effectiveStatus();

        if (! in_array($status, [Review::STATUS_PENDING, Review::STATUS_CHANGES_REQUESTED], true)) {
            return Response::error(match ($status) {
                Review::STATUS_APPROVED => 'The human approved this review — the conversation is closed. Say anything else in the chat.',
                Review::STATUS_EXPIRED => 'This review expired. Start a fresh create_review if you still need a checkup.',
                default => 'Replies are only open while the review is pending or has changes requested. Current status: '.$status.'.',
            });
        }

        ['updated' => $updated, 'skipped' => $skipped] = $this->lifecycle->addAgentReplies($review, $data['replies']);

        if ($updated->isEmpty()) {
            return Response::error('No replies were posted: '.self::describeSkipped($skipped));
        }

        $payload = $review->fresh()?->toAgentPayload();
        $payload['skipped_replies'] = $skipped;

        $warning = $skipped === [] ? '' : sprintf(
            "%d reply(ies) were NOT posted: %s\n\n",
            count($skipped),
            self::describeSkipped($skipped),
        );

        return Response::text(
            "Posted {$updated->count()} reply(ies). The human sees them on each mark's timeline.\n\n".
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
                ->description('List of {id, body}. id is the mark id from work_packets.pins[].id (not the M# number). body is what you want the human to read, in plain words.')
                ->required(),
        ];
    }
}
