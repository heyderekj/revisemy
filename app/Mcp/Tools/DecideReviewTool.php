<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Models\Review;
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
use Laravel\Mcp\Server\Ui\Enums\Visibility;

/**
 * App-only (Visibility::App): the human's approve / request-changes decision
 * from the inline review UI. Agents must never call this — a checkup is only
 * done when the human decides. Approving verifies resolved marks on this pass
 * and its parent.
 */
#[Name('decide_review')]
#[Description('HUMAN-IN-THE-LOOP UI ONLY — agents must never call this. Records the human approve / request-changes decision from the inline review app.')]
#[RendersApp(ReviewApp::class, visibility: [Visibility::App])]
class DecideReviewTool extends Tool
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
            'decision' => 'required|in:approved,changes_requested',
            'note' => 'nullable|string|max:5000',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['review_id']);

        if (! $review) {
            return Response::error('No review with that id for this token.');
        }

        $status = $data['decision'] === 'approved'
            ? Review::STATUS_APPROVED
            : Review::STATUS_CHANGES_REQUESTED;

        $note = isset($data['note']) ? FeedbackText::sanitizeBody($data['note']) : null;

        if (! $this->reviews->decide($review, $status, $note)) {
            return Response::error('This review is no longer open for a decision.');
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
            'decision' => $schema->string()->enum(['approved', 'changes_requested'])->description('The human decision')->required(),
            'note' => $schema->string()->description('Optional note explaining the decision'),
        ];
    }
}
