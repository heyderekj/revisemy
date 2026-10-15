<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Models\Review;
use App\Services\ReviewService;
use App\Services\ReviewWaiter;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Waiting on a person, as one call instead of a poll every 30 seconds. The
 * call holds until anything on the review moves (a decision, a mark, a
 * comment, a verify) or the hold runs out, and a host that asked for progress
 * hears a heartbeat while it waits. Hosts without MCP Tasks — every host,
 * today — get a long wait that still works over a plain tool call.
 */
#[Name('wait_for_review')]
#[Title('Wait for the review')]
#[Description('Wait for the person to act on a review instead of polling get_review. Returns as soon as anything changes (they decide, add a mark, comment, verify or reopen), or after about 45 seconds with changed:false; then call it again. Pass since = the updated_at you last saw (next_action.wait_with.since). Read-only. The result is the same record get_review returns.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class WaitForReviewTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
        protected ReviewWaiter $waiter,
    ) {}

    public function handle(Request $request): Response|ResponseFactory|Generator
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'since' => 'nullable|date',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        // Nothing given: wait for anything after this call started.
        $since = $this->waiter->since($review, $data['since'] ?? null);
        $token = $request->meta()['progressToken'] ?? null;

        if (! is_string($token) && ! is_int($token)) {
            return $this->result($review, $this->waiter->until($review, $since));
        }

        return (function () use ($review, $since, $token) {
            $watch = $this->waiter->watch($review, $since);
            $step = 0;

            foreach ($watch as $elapsed) {
                yield Response::notification('notifications/progress', [
                    'progressToken' => $token,
                    'progress' => ++$step,
                    'message' => 'Waiting for you on '.$review->title.' · '.gmdate('i:s', $elapsed),
                ]);
            }

            yield $this->result($review, $watch->getReturn());
        })();
    }

    protected function result(Review $review, bool $changed): ResponseFactory
    {
        $payload = ['changed' => $changed] + $review->fresh()->toAgentPayload();
        $next = $payload['next_action'];

        $lead = $changed
            ? 'Something changed on the review.'
            : 'Nothing changed yet. Call wait_for_review again with since = updated_at.';

        return Response::make(Response::text(
            $lead."\n".
            "Status: {$payload['status_label']}\n".
            "Next action: {$next['action']} — {$next['summary']}\n".
            "Review link: {$payload['review_url']}\n\n".
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        ))->withStructuredContent($payload);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id')->required(),
            'since' => $schema->string()->description('The updated_at you last saw (ISO 8601). The call returns once the review has moved past it. Leave it out to wait for anything after this call starts.'),
        ];
    }
}
