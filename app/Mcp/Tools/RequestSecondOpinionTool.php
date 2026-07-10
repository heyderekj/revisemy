<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\ReviewService;
use App\Services\SecondOpinionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('request_second_opinion')]
#[Description('Re-queue the Cloud second-opinion job (free checklist + optional OpenAI vision) for a review. Findings are suggestions only and never change review status.')]
class RequestSecondOpinionTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReviewService $reviews,
        protected SecondOpinionService $opinions,
    ) {}

    public function handle(Request $request): Response
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $data = $request->validate([
            'id' => 'required|string',
            'screenshot_index' => 'nullable|integer|min:0|max:4',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        try {
            $count = $this->opinions->requestForReview(
                $review,
                array_key_exists('screenshot_index', $data) ? (int) $data['screenshot_index'] : null,
            );
        } catch (ValidationException $e) {
            return Response::error(collect($e->errors())->flatten()->first() ?? 'Could not queue second opinion.');
        }

        return Response::text(
            "Queued second opinion for {$count} screenshot(s). Poll get_review until second_opinion_status is ready.\n\n".
            json_encode($review->fresh(['screenshots.annotations', 'screenshots.findings'])?->toAgentPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id')->required(),
            'screenshot_index' => $schema->integer()->description('Optional screenshot index; omit to refresh all shots'),
        ];
    }
}
