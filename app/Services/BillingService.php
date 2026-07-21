<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Checkout;
use RuntimeException;

class BillingService
{
    public function __construct(protected CreditsService $credits) {}

    public function stripeConfigured(): bool
    {
        return filled(config('cashier.secret'))
            && filled(config('billing.plans.pro.stripe_price'));
    }

    /**
     * @return array<string, mixed>
     */
    public function status(Workspace $workspace): array
    {
        $summary = $this->credits->summary($workspace);
        $summary['stripe_configured'] = $this->stripeConfigured();
        $summary['subscribed'] = $workspace->normalizedPlan() === Workspace::PLAN_PRO
            && $workspace->subscribed('default');
        $summary['checkout_available'] = $this->stripeConfigured()
            && $workspace->normalizedPlan() !== Workspace::PLAN_PRO;
        $summary['portal_available'] = $this->stripeConfigured() && filled($workspace->stripe_id);

        return $summary;
    }

    /**
     * Create a Stripe Checkout session for Pro. Returns the hosted URL.
     *
     * @throws RuntimeException
     */
    public function createCheckoutUrl(Workspace $workspace): string
    {
        if (! $this->stripeConfigured()) {
            throw new RuntimeException(
                '[billing_not_configured] Stripe is not configured on this ReviseMy host. Set STRIPE_SECRET and STRIPE_PRICE_PRO.',
            );
        }

        if ($workspace->normalizedPlan() === Workspace::PLAN_PRO && $workspace->subscribed('default')) {
            throw new RuntimeException(
                '[already_subscribed] This workspace is already on Pro. Call create_portal to manage billing.',
            );
        }

        $price = (string) config('billing.plans.pro.stripe_price');

        /** @var Checkout $checkout */
        $checkout = $workspace->newSubscription('default', $price)
            ->checkout([
                'success_url' => url('/billing/success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => url('/billing/cancel'),
                'client_reference_id' => $workspace->public_id,
                'metadata' => [
                    'workspace_public_id' => $workspace->public_id,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'workspace_public_id' => $workspace->public_id,
                        'type' => 'default',
                    ],
                ],
            ], array_filter([
                'email' => $workspace->stripeEmail(),
            ]));

        $url = $checkout->asStripeCheckoutSession()->url ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('[checkout_failed] Stripe did not return a checkout URL.');
        }

        return $url;
    }

    /**
     * @throws RuntimeException
     */
    public function createPortalUrl(Workspace $workspace): string
    {
        if (! $this->stripeConfigured()) {
            throw new RuntimeException(
                '[billing_not_configured] Stripe is not configured on this ReviseMy host.',
            );
        }

        if (! filled($workspace->stripe_id)) {
            throw new RuntimeException(
                '[no_customer] No Stripe customer on this workspace yet. Call create_checkout first.',
            );
        }

        return $workspace->billingPortalUrl(url('/billing/portal-return'));
    }

    /**
     * Sync plan + credits from the workspace's Cashier subscription state.
     */
    public function syncSubscriptionState(Workspace $workspace): void
    {
        $workspace->refresh();

        if ($workspace->subscribed('default')) {
            if ($workspace->normalizedPlan() !== Workspace::PLAN_PRO) {
                $this->credits->activatePro($workspace, $workspace->billing_email);
                $this->extendApiTokens($workspace);
                Log::info('Workspace upgraded to Pro', ['workspace' => $workspace->public_id]);
            }

            return;
        }

        if ($workspace->normalizedPlan() === Workspace::PLAN_PRO) {
            $this->credits->activateFree($workspace);
            Log::info('Workspace downgraded to Free', ['workspace' => $workspace->public_id]);
        }
    }

    /**
     * After Checkout, pull customer email and ensure Pro is active.
     */
    public function finalizeCheckout(Workspace $workspace, ?string $billingEmail = null): void
    {
        if ($billingEmail) {
            $workspace->forceFill(['billing_email' => $billingEmail])->save();
        }

        $this->credits->activatePro($workspace->fresh() ?? $workspace, $billingEmail);
        $this->extendApiTokens($workspace->fresh() ?? $workspace);
    }

    protected function extendApiTokens(Workspace $workspace): void
    {
        $days = (int) config('billing.plans.pro.token_days', 365);
        $expires = now()->addDays($days);

        $workspace->users()->each(function (User $user) use ($expires): void {
            $user->tokens()->update(['expires_at' => $expires]);
        });
    }
}
