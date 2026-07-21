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

#[Name('get_billing')]
#[Description('Show this workspace plan, credits remaining this month, and the burn table (images/pdf=1, html=3, capture_url=5). When credits are low or zero, call create_checkout and open checkout_url for the human. Full capture quality on Free and Pro — only the monthly credit grant differs.')]
class GetBillingTool extends Tool
{
    use ResolvesWorkspace;

    public function __construct(protected BillingService $billing) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->workspace($request);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $status = $this->billing->status($workspace);

        $lines = [
            "Plan: {$status['plan_name']} ({$status['plan']})",
            "Credits: {$status['credits_remaining']} / {$status['credits_grant']} this period",
            'Period ends: '.$status['credits_period_ends_at'],
            'Burn table: images/pdf='.$status['burn_table']['images'].', html='.$status['burn_table']['html'].', capture_url='.$status['burn_table']['capture_url'],
            'Review retention: '.$status['review_retention_days'].' days',
        ];

        if ($status['credits_remaining'] <= 0) {
            $lines[] = 'Credits exhausted — call create_checkout and open checkout_url for the human to upgrade to Pro ($'.$status['pro_price_usd'].'/mo, '.$status['pro_credits'].' credits).';
        } elseif ($status['checkout_available']) {
            $lines[] = 'Upgrade anytime with create_checkout (Pro $'.$status['pro_price_usd'].'/mo → '.$status['pro_credits'].' credits, same full quality).';
        }

        if ($status['portal_available'] && $status['plan'] === 'pro') {
            $lines[] = 'Manage subscription: call create_portal for a Stripe Billing Portal URL.';
        }

        return Response::make(Response::text(implode("\n", $lines)."\n\n".json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)))
            ->withStructuredContent($status);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
