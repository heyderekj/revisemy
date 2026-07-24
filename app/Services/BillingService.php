<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Laravel\Paddle\Cashier;
use Laravel\Paddle\Checkout;
use RuntimeException;

class BillingService
{
    public function __construct(protected CreditsService $credits) {}

    public function pricingEnabled(): bool
    {
        return (bool) config('billing.pricing_enabled', false);
    }

    public function paddleConfigured(): bool
    {
        return filled(config('cashier.api_key'))
            && filled(config('cashier.client_side_token'))
            && filled(config('billing.plans.pro.paddle_price'));
    }

    public function checkoutAvailable(Workspace $workspace): bool
    {
        return $this->pricingEnabled()
            && $this->paddleConfigured()
            && $workspace->normalizedPlan() !== Workspace::PLAN_PRO;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(Workspace $workspace): array
    {
        $summary = $this->credits->summary($workspace);
        $summary['pricing_enabled'] = $this->pricingEnabled();
        $summary['paddle_configured'] = $this->paddleConfigured();
        $summary['stripe_configured'] = false; // legacy key for older agents
        $summary['subscribed'] = $workspace->normalizedPlan() === Workspace::PLAN_PRO
            && $workspace->subscribed('default');
        $summary['checkout_available'] = $this->checkoutAvailable($workspace);
        $summary['portal_available'] = $this->pricingEnabled()
            && $this->paddleConfigured()
            && $workspace->customer !== null;

        return $summary;
    }

    /**
     * Signed URL to a page that opens Paddle Checkout for the human.
     *
     * @throws RuntimeException
     */
    public function createCheckoutUrl(Workspace $workspace): string
    {
        if (! $this->pricingEnabled()) {
            throw new RuntimeException(
                '[pricing_disabled] Paid Plus checkout is paused. Workspaces get '.
                (int) config('billing.plans.free.credits', 20).
                ' credits that renew monthly — call get_billing for remaining credits and when they refill.',
            );
        }

        if (! $this->paddleConfigured()) {
            throw new RuntimeException(
                '[billing_not_configured] Paddle is not configured on this ReviseMy host. Set PADDLE_API_KEY, PADDLE_CLIENT_SIDE_TOKEN, and PADDLE_PRICE_PRO.',
            );
        }

        if ($workspace->normalizedPlan() === Workspace::PLAN_PRO && $workspace->subscribed('default')) {
            throw new RuntimeException(
                '[already_subscribed] This workspace is already on Plus. Call create_portal to manage billing.',
            );
        }

        return URL::temporarySignedRoute(
            'billing.checkout',
            now()->addHours(6),
            ['workspace' => $workspace->public_id],
        );
    }

    /**
     * Build a guest Paddle Checkout (email collected in Paddle UI).
     *
     * @throws RuntimeException
     */
    public function checkoutForWorkspace(Workspace $workspace): Checkout
    {
        if (! $this->pricingEnabled()) {
            throw new RuntimeException('[pricing_disabled] Paid Plus checkout is paused.');
        }

        if (! $this->paddleConfigured()) {
            throw new RuntimeException('[billing_not_configured] Paddle is not configured.');
        }

        $price = (string) config('billing.plans.pro.paddle_price');

        return Checkout::guest([$price])
            ->customData([
                'subscription_type' => 'default',
                'workspace_public_id' => $workspace->public_id,
            ])
            ->returnTo(url('/billing/success').'?workspace='.$workspace->public_id);
    }

    /**
     * Options for Paddle.Checkout.open (overlay).
     *
     * @return array<string, mixed>
     */
    public function checkoutOpenOptions(Workspace $workspace): array
    {
        $options = $this->checkoutForWorkspace($workspace)->options();
        $options['settings']['displayMode'] = 'inline';
        $options['settings']['frameTarget'] = 'paddle-checkout';
        $options['settings']['frameInitialHeight'] = '516';
        $options['settings']['frameStyle'] = 'width: 100%; min-width: 312px; background-color: transparent; border: none;';
        $options['settings']['variant'] = 'one-page';

        return $options;
    }

    /**
     * Signed URL to manage / cancel Plus.
     *
     * @throws RuntimeException
     */
    public function createPortalUrl(Workspace $workspace): string
    {
        if (! $this->pricingEnabled()) {
            throw new RuntimeException(
                '[pricing_disabled] Paid billing is paused. Call get_billing for monthly credits.',
            );
        }

        if (! $this->paddleConfigured()) {
            throw new RuntimeException(
                '[billing_not_configured] Paddle is not configured on this ReviseMy host.',
            );
        }

        if ($workspace->customer === null && $workspace->normalizedPlan() !== Workspace::PLAN_PRO) {
            throw new RuntimeException(
                '[no_customer] No Paddle customer on this workspace yet. Call create_checkout first.',
            );
        }

        return URL::temporarySignedRoute(
            'billing.manage',
            now()->addHours(6),
            ['workspace' => $workspace->public_id],
        );
    }

    /**
     * Link a Paddle customer to a workspace from webhook custom_data (before Cashier runs).
     *
     * @param  array<string, mixed>  $data
     */
    public function linkWorkspaceFromPaddlePayload(array $data): ?Workspace
    {
        $publicId = $data['custom_data']['workspace_public_id'] ?? null;
        $paddleCustomerId = $data['customer_id'] ?? null;

        if (! is_string($publicId) || $publicId === '' || ! is_string($paddleCustomerId) || $paddleCustomerId === '') {
            return null;
        }

        $workspace = Workspace::query()->where('public_id', $publicId)->first();

        if (! $workspace) {
            return null;
        }

        if ($workspace->customer) {
            return $workspace;
        }

        try {
            $remote = Cashier::api('GET', "customers/{$paddleCustomerId}")['data'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Paddle customer fetch failed', [
                'paddle_customer' => $paddleCustomerId,
                'error' => $e->getMessage(),
            ]);
            $remote = null;
        }

        $email = is_array($remote)
            ? (string) ($remote['email'] ?? '')
            : '';
        $name = is_array($remote)
            ? (string) ($remote['name'] ?? $workspace->name)
            : (string) $workspace->name;

        if ($email === '') {
            $email = $workspace->billing_email ?: 'workspace-'.$workspace->public_id.'@revisemy.local';
        }

        if (Cashier::$customerModel::query()->where('paddle_id', $paddleCustomerId)->exists()) {
            return $workspace;
        }

        $workspace->customer()->create([
            'paddle_id' => $paddleCustomerId,
            'name' => $name !== '' ? $name : 'ReviseMy workspace',
            'email' => $email,
        ]);

        if (! str_ends_with($email, '@revisemy.local')) {
            $workspace->forceFill(['billing_email' => $email])->save();
        }

        return $workspace->fresh();
    }

    public function syncSubscriptionState(Workspace $workspace): void
    {
        $workspace->refresh();

        if ($workspace->subscribed('default')) {
            if ($workspace->normalizedPlan() !== Workspace::PLAN_PRO) {
                $this->credits->activatePro($workspace, $workspace->billing_email);
                $this->extendApiTokens($workspace);
                Log::info('Workspace upgraded to Plus', ['workspace' => $workspace->public_id]);
            }

            return;
        }

        if ($workspace->normalizedPlan() === Workspace::PLAN_PRO) {
            $this->credits->activateFree($workspace);
            Log::info('Workspace downgraded to Try', ['workspace' => $workspace->public_id]);
        }
    }

    public function finalizeCheckout(Workspace $workspace, ?string $billingEmail = null): void
    {
        if ($billingEmail) {
            $workspace->forceFill(['billing_email' => $billingEmail])->save();
        }

        $this->credits->activatePro($workspace->fresh() ?? $workspace, $billingEmail);
        $this->extendApiTokens($workspace->fresh() ?? $workspace);
    }

    public function cancelPro(Workspace $workspace): void
    {
        $subscription = $workspace->subscription('default');

        if (! $subscription || $subscription->canceled()) {
            throw new RuntimeException(
                '[not_subscribed] No active Plus subscription to cancel. Call get_billing to check plan, or create_portal if they need Paddle receipts.',
            );
        }

        $subscription->cancel();
        $this->syncSubscriptionState($workspace->fresh() ?? $workspace);
    }

    /**
     * Push all workspace API tokens to a new absolute expiry (Plus upgrade).
     */
    protected function extendApiTokens(Workspace $workspace): void
    {
        $days = (int) config('billing.plans.pro.token_days', 365);
        $this->setApiTokenExpiry($workspace, now()->addDays($days));
    }

    /**
     * Extend each token from max(now, current expiry) by $days (support / try top-up).
     */
    public function extendApiTokensByDays(Workspace $workspace, int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $workspace->users()->each(function (User $user) use ($days): void {
            $user->tokens()->each(function ($token) use ($days): void {
                $base = $token->expires_at && $token->expires_at->isFuture()
                    ? $token->expires_at
                    : now();
                $token->forceFill(['expires_at' => $base->copy()->addDays($days)])->save();
            });
        });
    }

    protected function setApiTokenExpiry(Workspace $workspace, $expires): void
    {
        $workspace->users()->each(function (User $user) use ($expires): void {
            $user->tokens()->update(['expires_at' => $expires]);
        });
    }
}
