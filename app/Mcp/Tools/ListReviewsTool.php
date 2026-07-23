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
#[Description('List recent design reviews for this try token only. Returns pass #, status, next_action, and outstanding / awaiting-verification counts — not full work packets. Call get_review for pins.')]
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
            ->with(['screenshots.annotations', 'parent'])
            ->get()
            ->map(fn (Review $review) => $review->toListSummary())
            ->values();

        return Response::structured([
            'reviews' => $reviews,
            'count' => $reviews->count(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
