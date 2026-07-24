<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesWorkspace;
use App\Services\BillingService;
use App\Support\BrandAssets;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('create_portal')]
#[Description('Open the billing manage page so the human can update payment method, view receipts, or cancel Plus. Returns portal_url — immediately paste it into chat as the markdown share block (never only say “open the billing page”).')]
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

        $share = BrandAssets::markdownShareLink($url);
        $payload = [
            'portal_url' => $url,
            'share_markdown' => $share,
            'next_action' => 'share_portal_url',
            'hint' => 'Paste share_markdown (or portal_url as a markdown link + backticks) into the human-visible chat immediately. Do not only say “open the billing page.”',
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return Response::make(Response::text(
            "Billing manage page ready — share this link with the human now:\n".
            "{$share}\n\n".
            "```json\n{$json}\n```"
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
