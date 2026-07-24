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
#[Description('Show this workspace plan, credits remaining, and the burn table (images/pdf=1, html=3, capture_url=5). Default Try pack is 20 credits that renew monthly (no rollover). When credits are zero, wait for the monthly refill — call get_billing for the period end. Paid Plus checkout is only available when pricing_enabled is true.')]
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
        $renews = (bool) ($status['credits_renew'] ?? false);
        $periodLine = $renews && filled($status['credits_period_ends_at'] ?? null)
            ? 'Period ends: '.$status['credits_period_ends_at']
            : 'Credits: one-time pack (no monthly reset)';

        $lines = [
            "Plan: {$status['plan_name']} ({$status['plan']})",
            "Credits: {$status['credits_remaining']} / {$status['credits_grant']}".($renews ? ' this period' : ' (try pack)'),
            $periodLine,
            'Burn table: images/pdf='.$status['burn_table']['images'].', html='.$status['burn_table']['html'].', capture_url='.$status['burn_table']['capture_url'],
            'Review retention: '.$status['review_retention_days'].' days',
        ];

        if ($status['credits_remaining'] <= 0) {
            if ($status['checkout_available'] ?? false) {
                $lines[] = 'Credits exhausted — call create_checkout and immediately paste share_markdown / checkout_url into chat for the human (Plus $'.$status['pro_price_usd'].'/mo, '.$status['pro_credits'].' credits/mo). Do not only say “finish payment in the browser.”';
            } elseif ($renews) {
                $lines[] = 'Credits exhausted — wait for the monthly refill (see period end above). Paid upgrade is paused.';
            } else {
                $lines[] = 'Credits exhausted — this pack does not refill.';
            }
        } elseif ($status['checkout_available'] ?? false) {
            $lines[] = 'Upgrade anytime with create_checkout — paste the returned share_markdown into chat (Plus $'.$status['pro_price_usd'].'/mo → '.$status['pro_credits'].' credits/mo, same full quality).';
        } elseif (! ($status['pricing_enabled'] ?? false) && $renews) {
            $lines[] = 'Credits renew monthly (no rollover). Paid Plus checkout is paused while pricing is figured out.';
        }

        if (($status['portal_available'] ?? false) && $status['plan'] === 'pro') {
            $lines[] = 'To cancel Plus: call cancel_subscription with confirm:true (keeps Plus until period end, then Try with no new credit grant). For payment method / receipts: create_portal.';
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
