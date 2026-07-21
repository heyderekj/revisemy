<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\BillingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('create_portal')]
#[Description('Open the Stripe Billing Portal so the human can update payment method or cancel Pro. Returns portal_url.')]
class CreatePortalTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected BillingService $billing) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        try {
            $url = $this->billing->createPortalUrl($workspace);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $payload = [
            'portal_url' => $url,
            'next_action' => 'open_portal',
            'hint' => 'Open portal_url for the human to manage their subscription.',
        ];

        return Response::make(Response::text(
            "Open the Stripe Billing Portal for the human:\n{$url}\n\n".
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        ))->withStructuredContent($payload);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
