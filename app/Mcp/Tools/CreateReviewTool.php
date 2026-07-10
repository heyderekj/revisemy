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
#[Description('Create a design review from one or more UI screenshots. Queues a Cloud second-opinion pass automatically. Returns a laravel.cloud review URL. Optionally pass page_url and call add_findings as a design-reviewer subagent before the human looks.')]
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
            'page_url' => 'nullable|string|max:2048',
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
                $data['page_url'] ?? null,
            );
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not create the review.');
        }

        $payload = $review->toAgentPayload();

        return Response::text(
            "Review created — waiting on your eye. A second-opinion job is queued on Laravel Cloud.\n\n".
            "Optional: call add_findings with your own critique (suggestion/a11y/polish) before sharing the link.\n\n".
            "Open this link and pin feedback:\n{$payload['review_url']}\n\n".
            "Then call get_review with id `{$payload['id']}` to read the decision, pins, and second_opinion work packets.\n\n".
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
            'page_url' => $schema->string()->description('Optional live page URL for future DOM grounding'),
            'images' => $schema->array()
                ->items($schema->string()->description('Screenshot as https URL, data URL, or base64'))
                ->min(1)
                ->max(5)
                ->description('One to five screenshots of the UI')
                ->required(),
        ];
    }
}
