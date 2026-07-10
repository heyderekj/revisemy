<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_review')]
#[Description('Create a design review from one or more UI screenshots. Returns a laravel.cloud review URL for the human to pin feedback and approve or request changes. Pass images as https URLs, data URLs, or base64.')]
class CreateReviewTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected ReviewService $reviews) {}

    public function handle(Request $request): Response
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'title' => 'required|string|max:160',
            'context' => 'nullable|string|max:5000',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'required|string',
        ], [
            'title.required' => 'Give the review a short title — what should they look at?',
            'images.required' => 'Include at least one screenshot.',
        ]);

        try {
            $review = $this->reviews->create(
                $workspace,
                $data['title'],
                $data['context'] ?? null,
                $data['images'],
            );
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not create the review.');
        }

        $payload = $review->toAgentPayload();

        return Response::text(
            "Review created — waiting on your eye.\n\n".
            "Open this link and pin feedback:\n{$payload['review_url']}\n\n".
            "Then call get_review with id `{$payload['id']}` to read the decision and pins.\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Short title for the review')->required(),
            'context' => $schema->string()->description('What should they look at? Optional notes for the human.'),
            'images' => $schema->array()
                ->items($schema->string()->description('Screenshot as https URL, data URL, or base64'))
                ->min(1)
                ->max(5)
                ->description('One to five screenshots of the UI')
                ->required(),
        ];
    }
}
