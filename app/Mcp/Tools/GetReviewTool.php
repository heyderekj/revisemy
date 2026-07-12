<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Mcp\Resources\ReviewApp;
use App\Services\ReviewService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_review')]
#[Description('Poll the design checkup: status, next_action, human marks in work_packets.pins (authoritative), second_opinion hints. Follow next_action — wait, apply marks + create next pass, or stop when approved. In MCP Apps hosts this renders the review inline so the human can mark and decide there.')]
#[IsReadOnly]
#[RendersApp(ReviewApp::class)]
class GetReviewTool extends Tool
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
            'id' => 'required|string',
        ]);

        $review = $this->reviews->findForWorkspace($workspace, $data['id']);

        if (! $review) {
            return Response::error('No review with that id for this try token.');
        }

        $payload = $review->toAgentPayload();
        $next = $payload['next_action'];

        return Response::make(Response::text(
            "Status: {$payload['status_label']}\n".
            "Next action: {$next['action']} — {$next['summary']}\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ))->withStructuredContent($payload);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The review public id returned by create_review')->required(),
        ];
    }
}
