<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Models\Annotation;
use App\Models\AnnotationEvent;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Support\FeedbackText;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * App-only (Visibility::App): the human answers on a mark's timeline from the
 * inline review. The token holder is the owner, as on the web review page.
 * The agent replies with reply_to_marks instead.
 */
#[Name('add_comment')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents must never call this; use reply_to_marks. Posts the owner\'s comment on a mark from the inline review app.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class AddCommentTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
        protected MarkLifecycleService $lifecycle,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'review_id' => 'required|string',
            'mark_id' => 'required|integer',
            'body' => FeedbackText::bodyRules(),
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);

        if (! $review) {
            return Response::error('No review with that id for this token.');
        }

        if (! $review->allowsComments()) {
            return Response::error('Comments are off for this review. Turn them back on from the review page.');
        }

        $mark = Annotation::query()
            ->whereKey($data['mark_id'])
            ->whereHas('screenshot', fn ($q) => $q->whereIn('review_id', array_filter([$review->id, $review->parent_id])))
            ->first();

        if (! $mark) {
            return Response::error('No mark with that id on this review.');
        }

        $body = FeedbackText::sanitizeBody($data['body']);

        if ($body === '') {
            return Response::error('Write something to send.');
        }

        $this->lifecycle->addComment($mark, AnnotationEvent::ACTOR_OWNER, $body);

        return Response::structured($review->refresh()->toAgentPayload());
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->description('The review public id')->required(),
            'mark_id' => $schema->integer()->description('The mark (annotation) id')->required(),
            'body' => $schema->string()->description('The owner\'s comment')->required(),
        ];
    }
}
