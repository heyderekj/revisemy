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
#[Description('Start or continue a design checkup loop: upload screenshots, get a review URL for the human. Pass parent_id after changes_requested to open the next pass with new shots of the fixed UI. Optional page_url; call add_findings before sharing if you want a subagent critique.')]
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
            'parent_id' => 'nullable|string',
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
                $data['parent_id'] ?? null,
            );
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not create the review.');
        }

        $payload = $review->toAgentPayload();
        $passLabel = $payload['pass'] > 1 ? " (pass {$payload['pass']})" : '';

        return Response::text(
            "Review created{$passLabel} — waiting on the human.\n\n".
            "Loop: share the link → human pins + decides → you poll get_review → follow next_action.\n\n".
            "Optional: call add_findings (suggestion/a11y/polish) before sharing.\n\n".
            "Open this link:\n{$payload['review_url']}\n\n".
            "Then poll get_review with id `{$payload['id']}`.\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Short title for this checkup pass')->required(),
            'context' => $schema->string()->description('What should they look at? Optional notes for the human.'),
            'page_url' => $schema->string()->description('Optional live page URL for future DOM grounding'),
            'parent_id' => $schema->string()->description('Previous review id when opening the next pass after changes_requested'),
            'images' => $schema->array()
                ->items($schema->string()->description('Screenshot as https URL, data URL, or base64'))
                ->min(1)
                ->max(5)
                ->description('One to five screenshots of the UI')
                ->required(),
        ];
    }
}
