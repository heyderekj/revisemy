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
#[Description('Show this workspace plan, credits remaining, and the burn table (images/pdf=1, html=3, capture_url=5). Try is 20 credits that renew monthly (no rollover); purchased pack credits never expire and are spent last. When credits run out and checkout is available, offer Plus or a credit pack via create_checkout; otherwise wait for the monthly refill.')]
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
            "Credits: {$status['credits_remaining']} total — {$status['credits_monthly']} / {$status['credits_grant']} monthly".($renews ? ' this period' : '').
                ($status['credits_purchased'] > 0 ? " + {$status['credits_purchased']} purchased (never expire)" : ''),
            $periodLine,
            'Burn table: images/pdf='.$status['burn_table']['images'].', html='.$status['burn_table']['html'].', capture_url='.$status['burn_table']['capture_url'],
            'Review retention: '.$status['review_retention_days'].' days',
        ];

        if ($status['credits_remaining'] <= 0) {
            if (($status['checkout_available'] ?? false) || ($status['pack_checkout_available'] ?? false)) {
                $lines[] = 'Credits exhausted — offer the human '.$this->offer($status).'. Call create_checkout with their choice and immediately paste share_markdown / checkout_url into chat. Do not only say “finish payment in the browser.”';
            } elseif ($renews) {
                $lines[] = 'Credits exhausted — wait for the monthly refill (see period end above). Paid upgrade is paused.';
            } else {
                $lines[] = 'Credits exhausted — this pack does not refill.';
            }
        } elseif (($status['checkout_available'] ?? false) || ($status['pack_checkout_available'] ?? false)) {
            $lines[] = 'More credits anytime: '.$this->offer($status).' via create_checkout — paste the returned share_markdown into chat.';
        } elseif (! ($status['pricing_enabled'] ?? false) && $renews) {
            $lines[] = 'Credits renew monthly (no rollover). Paid Plus checkout is paused while pricing is figured out.';
        }

        if (($status['subscribed'] ?? false) && ($status['cancel_at_period_end'] ?? false)) {
            $lines[] = 'Plus is canceled and ends at the period end above; then Try.';
        } elseif (($status['portal_available'] ?? false) && ($status['subscribed'] ?? false)) {
            $lines[] = 'To cancel Plus: call cancel_subscription with confirm:true (keeps Plus until period end, then Try). For payment method / receipts: create_portal.';
        }

        return Response::make(Response::text(implode("\n", $lines)."\n\n".json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)))
            ->withStructuredContent($status);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function offer(array $status): string
    {
        $options = [];

        if ($status['checkout_available'] ?? false) {
            $options[] = 'Plus (product "plus", $'.$status['pro_price_usd'].'/mo → '.$status['pro_credits'].' credits/mo)';
        }

        if ($status['pack_checkout_available'] ?? false) {
            foreach ($status['packs'] ?? [] as $pack) {
                $options[] = 'a '.$pack['credits'].'-credit pack (product "'.$pack['product'].'", $'.$pack['price_usd'].' once, never expires)';
            }
        }

        return implode(' or ', $options);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
