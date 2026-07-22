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

#[Name('create_checkout')]
#[Description('Start Paddle Checkout for Pro ($9/mo, 100 credits, full quality). Returns checkout_url — open it for the human (Paddle collects email + payment). After they pay, continue with create_review. Prefer this when create_review returns [insufficient_credits].')]
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

        $payload = [
            'checkout_url' => $url,
            'plan' => 'pro',
            'price_usd' => (int) config('billing.plans.pro.price_usd', 9),
            'credits_grant' => (int) config('billing.plans.pro.credits', 100),
            'next_action' => 'open_checkout',
            'hint' => 'Open checkout_url in the browser for the human. After payment they return to the agent — call create_review again.',
        ];

        return Response::make(Response::text(
            "Open this Paddle Checkout link for the human to upgrade to Pro:\n{$url}\n\n".
            "After they finish, call create_review again (or get_billing to confirm credits).\n\n".
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
