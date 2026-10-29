<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Models\Review;
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

#[Name('list_reviews')]
#[Title('List reviews')]
#[Description('List recent design reviews for this try token only, newest first (20). Returns pass #, status, next_action, archived, and outstanding / awaiting-verification counts — not full work packets. Archived reviews are left out unless include_archived is true; status narrows to one status. Call get_review for pins.')]
#[IsReadOnly]
#[IsOpenWorld(false)]
class ListReviewsTool extends Tool
{
    use ResolvesWorkspace;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'status' => 'nullable|in:'.implode(',', Review::LIST_STATUSES),
            'include_archived' => 'nullable|boolean',
        ]);

        $reviews = $workspace->reviews()
            ->when(! ($data['include_archived'] ?? false), fn ($q) => $q->unarchived())
            ->when($data['status'] ?? null, fn ($q, string $status) => $q->withStatus($status))
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
        return [
            'status' => $schema->string()->enum(Review::LIST_STATUSES)->description('Only reviews with this status'),
            'include_archived' => $schema->boolean()->description('Include reviews the person archived (default false)'),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'reviews' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'status' => $schema->string()->required(),
                'review_url' => $schema->string()->required(),
            ]))->required(),
            'count' => $schema->integer()->required(),
        ];
    }
}
