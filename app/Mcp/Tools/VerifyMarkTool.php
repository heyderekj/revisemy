<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Models\Annotation;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * App-only (Visibility::App): the human verifies or reopens a resolved mark
 * from the inline review UI. Verify and reopen are human-only in the lifecycle
 * — agents must never call this.
 */
#[Name('verify_mark')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents must never call this. Verifies or reopens a mark from the inline review app.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
class VerifyMarkTool extends Tool
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
            'action' => 'required|in:verify,reopen',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);

        if (! $review) {
            return Response::error('No review with that id for this token.');
        }

        if (! $review->allowsMarkManagement()) {
            return Response::error('This review is '.$review->effectiveStatus().' — marks can no longer be verified or reopened here.');
        }

        // Pass 2+ shows the previous pass's marks for verification, so reach
        // one level up the chain (same as the web review page).
        $mark = Annotation::query()
            ->whereKey($data['mark_id'])
            ->whereHas('screenshot', fn ($q) => $q->whereIn('review_id', array_filter([$review->id, $review->parent_id])))
            ->first();

        if (! $mark) {
            return Response::error('No mark with that id on this review.');
        }

        $changed = $data['action'] === 'verify'
            ? $this->lifecycle->verify($mark)
            : $this->lifecycle->reopen($mark);

        if (! $changed) {
            return Response::error($data['action'] === 'verify'
                ? 'Only a resolved mark can be verified.'
                : 'That mark is already open.');
        }

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
            'action' => $schema->string()->enum(['verify', 'reopen'])->description('verify a resolved mark, or reopen any mark')->required(),
        ];
    }
}
