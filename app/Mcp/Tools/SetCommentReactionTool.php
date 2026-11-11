<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Models\AnnotationComment;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

#[Name('set_comment_reaction')]
#[Title('React to a comment')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents must never call this. Sets or removes the owner’s reaction to a comment from the inline review.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
#[IsDestructive(false)]
#[IsIdempotent]
#[IsOpenWorld(false)]
class SetCommentReactionTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected ReviewService $reviews) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'review_id' => 'required|string',
            'comment_id' => 'required|integer',
            'reaction' => ['required', Rule::in(array_keys(AnnotationComment::REACTIONS))],
            'selected' => 'required|boolean',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);

        if (! $review) {
            return Response::error('No review with that id for this token.');
        }

        if (! $review->allowsComments()) {
            return Response::error('Reactions are off for this review.');
        }

        $comment = AnnotationComment::query()
            ->whereKey($data['comment_id'])
            ->whereHas('annotation.screenshot', fn ($query) => $query->whereIn('review_id', array_filter([$review->id, $review->parent_id])))
            ->first();

        if (! $comment) {
            return Response::error('No comment with that id on this review.');
        }

        $comment->setReaction($data['reaction'], AnnotationComment::AUTHOR_OWNER, (bool) $data['selected']);

        return Response::structured($review->toAgentPayload());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'review_id' => $schema->string()->description('The review public id')->required(),
            'comment_id' => $schema->integer()->description('The comment id in the mark timeline')->required(),
            'reaction' => $schema->string()->enum(array_keys(AnnotationComment::REACTIONS))->required(),
            'selected' => $schema->boolean()->description('Whether the owner should have this reaction selected')->required(),
        ];
    }
}
