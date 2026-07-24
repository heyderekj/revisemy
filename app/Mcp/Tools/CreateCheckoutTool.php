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

#[Name('create_checkout')]
#[Description('Start Paddle Checkout for Plus when paid pricing is enabled. Often returns [pricing_disabled] — in that case do not ask the human to pay; tell them credits renew monthly and call get_billing. When checkout is available: immediately paste share_markdown / checkout_url into chat (never only say “finish payment in the browser”).')]
class CreateCheckoutTool extends Tool
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
            $url = $this->billing->createCheckoutUrl($workspace);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        $share = BrandAssets::markdownShareLink($url);
        $payload = [
            'checkout_url' => $url,
            'share_markdown' => $share,
            'plan' => 'pro',
            'price_usd' => (int) config('billing.plans.pro.price_usd', 9),
            'credits_grant' => (int) config('billing.plans.pro.credits', 100),
            'next_action' => 'share_checkout_url',
            'hint' => 'Paste share_markdown (or checkout_url as a markdown link + backticks) into the human-visible chat immediately. Do not only say “finish payment in the browser.” On Cursor, also call open_resource with checkout_url. After payment, call get_billing then create_review again.',
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return Response::make(Response::text(
            "Plus checkout ready — share this link with the human now (do not only say “finish payment in the browser”):\n".
            "{$share}\n\n".
            "After they pay, call get_billing to confirm credits, then continue.\n\n".
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
