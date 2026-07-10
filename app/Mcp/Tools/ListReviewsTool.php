<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Models\Review;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_reviews')]
#[Description('List recent design reviews for this try token only.')]
#[IsReadOnly]
class ListReviewsTool extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $reviews = $workspace->reviews()
            ->latest()
            ->limit(20)
            ->with(['screenshots.annotations'])
            ->get()
            ->map(fn (Review $review) => [
                'id' => $review->public_id,
                'title' => $review->title,
                'pass' => $review->pass,
                'status' => $review->effectiveStatus(),
                'status_label' => $review->toAgentPayload()['status_label'],
                'next_action' => $review->nextAction()['action'],
                'review_url' => $review->reviewUrl(),
                'created_at' => $review->created_at?->toIso8601String(),
            ]);

        return Response::text(json_encode(['reviews' => $reviews], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
