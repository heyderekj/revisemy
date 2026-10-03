<?php

namespace App\Services;

use App\Models\BillingOrder;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class BillingService
{
    public const PRODUCT_PLUS = 'plus';

    public function __construct(
        protected CreditsService $credits,
        protected PolarClient $polar,
    ) {}

    public function pricingEnabled(): bool
    {
        return (bool) config('billing.pricing_enabled', false);
    }

    public function polarConfigured(): bool
    {
        return $this->polar->configured();
    }

    /**
     * Product keys a checkout can sell: Plus plus every configured pack.
     *
     * @return list<string>
     */
    public function productKeys(): array
    {
        return [self::PRODUCT_PLUS, ...array_keys((array) config('billing.packs', []))];
    }

    public function isPack(string $product): bool
    {
        return array_key_exists($product, (array) config('billing.packs', []));
    }

    /**
     * What a checkout for $product sells, for agent payloads.
     *
     * @return array<string, mixed>
     */
    public function checkoutDetails(string $product): array
    {
        if ($product === self::PRODUCT_PLUS) {
            return [
                'product' => $product,
                'plan' => Workspace::PLAN_PRO,
                'price_usd' => (int) config('billing.plans.pro.price_usd', 9),
                'credits_grant' => (int) config('billing.plans.pro.credits', 100),
                'recurring' => 'monthly',
            ];
        }

        return [
            'product' => $product,
            'price_usd' => (int) config("billing.packs.{$product}.price_usd", 0),
            'credits_grant' => (int) config("billing.packs.{$product}.credits", 0),
            'recurring' => false,
        ];
    }

    public function checkoutAvailable(Workspace $workspace): bool
    {
        return $this->pricingEnabled()
            && $this->polarConfigured()
            && ! $workspace->isPlusActive();
    }

    public function packCheckoutAvailable(): bool
    {
        return $this->pricingEnabled()
            && $this->polarConfigured()
            && collect(array_keys((array) config('billing.packs', [])))
                ->contains(fn (string $key) => $this->polar->productId($key) !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(Workspace $workspace): array
    {
        $summary = $this->credits->summary($workspace);
        $summary['pricing_enabled'] = $this->pricingEnabled();
        $summary['polar_configured'] = $this->polarConfigured();
        $summary['subscribed'] = $workspace->isPlusActive();
        $summary['cancel_at_period_end'] = (bool) $workspace->polar_cancel_at_period_end;
        $summary['checkout_available'] = $this->checkoutAvailable($workspace);
        $summary['pack_checkout_available'] = $this->packCheckoutAvailable();
        $summary['portal_available'] = $this->pricingEnabled()
            && $this->polarConfigured()
            && $this->hasPolarCustomer($workspace);

        return $summary;
    }

    /**
     * Signed URL that starts a Polar checkout for the human. Signed (not a raw
     * Polar session URL) so an agent can hold it for hours without it expiring.
     *
     * @throws RuntimeException
     */
    public function createCheckoutUrl(Workspace $workspace, string $product = self::PRODUCT_PLUS): string
    {
        $this->assertCanCheckout($workspace, $product);

        return URL::temporarySignedRoute(
            'billing.checkout',
            now()->addHours(6),
            ['workspace' => $workspace->public_id, 'product' => $product],
        );
    }

    /**
     * Create the Polar checkout session and return its URL.
     *
     * @throws RuntimeException
     */
    public function startCheckout(Workspace $workspace, string $product = self::PRODUCT_PLUS): string
    {
        $this->assertCanCheckout($workspace, $product);

        $productId = (string) $this->polar->productId($product);
        $session = $this->polar->createCheckout(
            productId: $productId,
            externalCustomerId: $workspace->public_id,
            successUrl: url('/billing/success').'?checkout_id={CHECKOUT_ID}&product='.$product,
            returnUrl: url('/billing/cancel'),
            email: $workspace->billing_email,
            metadata: [
                'workspace_public_id' => $workspace->public_id,
                'product' => $product,
            ],
        );

        return $session['url'];
    }

    /**
     * The Fathom event for a purchase, from what Polar's return URL carries.
     *
     * The value comes from config, never from the URL, and a missing or
     * malformed checkout id means no event. This is the page a buyer lands on,
     * not proof of payment (Polar is the ledger), so it only has to be hard to
     * trigger by accident.
     *
     * @return array{name: string, value: int, once: string}|null
     */
    public function purchaseEvent(?string $checkoutId, ?string $product): ?array
    {
        if (! is_string($checkoutId) || ! is_string($product)
            || ! preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $checkoutId)) {
            return null;
        }

        if ($product === self::PRODUCT_PLUS) {
            return [
                'name' => 'Plus purchased',
                'value' => (int) config('billing.plans.pro.price_usd', 9) * 100,
                'once' => $checkoutId,
            ];
        }

        if ($this->isPack($product)) {
            return [
                'name' => 'Credit pack purchased',
                'value' => (int) config("billing.packs.{$product}.price_usd", 0) * 100,
                'once' => $checkoutId,
            ];
        }

        return null;
    }

    /**
     * @throws RuntimeException
     */
    protected function assertCanCheckout(Workspace $workspace, string $product): void
    {
        if (! $this->pricingEnabled()) {
            throw new RuntimeException(
                '[pricing_disabled] Paid checkout is paused. Workspaces get '.
                (int) config('billing.plans.free.credits', 20).
                ' credits that renew monthly — call get_billing for remaining credits and when they refill.',
            );
        }

        if (! in_array($product, $this->productKeys(), true)) {
            throw new RuntimeException(
                '[unknown_product] product must be one of: '.implode(', ', $this->productKeys()).'.',
            );
        }

        if (! $this->polarConfigured() || $this->polar->productId($product) === null) {
            throw new RuntimeException(
                '[billing_not_configured] Polar is not configured on this ReviseMy host. Set POLAR_ACCESS_TOKEN, POLAR_WEBHOOK_SECRET, and the POLAR_PRODUCT_* IDs.',
            );
        }

        if ($product === self::PRODUCT_PLUS && $workspace->isPlusActive()) {
            throw new RuntimeException(
                '[already_subscribed] This workspace is already on Plus. Buy a credit pack with product: "'.
                (array_key_first((array) config('billing.packs', [])) ?? 'credits_50').
                '" for more credits, or call create_portal to manage billing.',
            );
        }
    }

    /**
     * Signed URL to the billing manage page (receipts, card, cancel Plus).
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

        if (! $this->polarConfigured()) {
            throw new RuntimeException(
                '[billing_not_configured] Polar is not configured on this ReviseMy host.',
            );
        }

        if (! $this->hasPolarCustomer($workspace)) {
            throw new RuntimeException(
                '[no_customer] This workspace has not bought anything yet. Call create_checkout first.',
            );
        }

        return URL::temporarySignedRoute(
            'billing.manage',
            now()->addHours(6),
            ['workspace' => $workspace->public_id],
        );
    }

    /**
     * Fresh Polar customer-portal URL (expires quickly, so mint on click).
     *
     * @throws RuntimeException
     */
    public function polarPortalUrl(Workspace $workspace): string
    {
        if (! $this->hasPolarCustomer($workspace)) {
            throw new RuntimeException('[no_customer] This workspace has not bought anything yet.');
        }

        return $this->polar->customerPortalUrl($workspace->public_id);
    }

    public function hasPolarCustomer(Workspace $workspace): bool
    {
        return $workspace->polar_customer_id !== null || $workspace->billingOrders()->exists();
    }

    /**
     * Stop Plus renewal. Access continues until Polar revokes the subscription
     * at period end (subscription.revoked webhook).
     *
     * @throws RuntimeException
     */
    public function cancelPro(Workspace $workspace): void
    {
        if (! $workspace->isPlusActive()) {
            throw new RuntimeException(
                '[not_subscribed] No active Plus subscription to cancel. Call get_billing to check the plan.',
            );
        }

        if ($workspace->polar_cancel_at_period_end) {
            return;
        }

        $this->polar->cancelAtPeriodEnd((string) $workspace->polar_subscription_id);
        $workspace->forceFill(['polar_cancel_at_period_end' => true])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Webhook handling
    |--------------------------------------------------------------------------
    */

    /**
     * Apply one verified Polar webhook event. Unknown events, or events for a
     * workspace we can't find, are ignored so Polar does not retry them.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleWebhook(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        $workspace = $this->workspaceFromPayload($data);

        if (! $workspace) {
            // A paid order we can't tie to a workspace is a customer who paid and got nothing.
            Log::log(
                $type === 'order.paid' ? 'warning' : 'info',
                'Polar webhook ignored: no matching workspace',
                ['type' => $type, 'id' => $data['id'] ?? null],
            );

            return;
        }

        match ($type) {
            'order.paid' => $this->handleOrderPaid($workspace, $data),
            'order.refunded' => $this->handleOrderRefunded($workspace, $data),
            'subscription.created',
            'subscription.active',
            'subscription.updated',
            'subscription.canceled',
            'subscription.uncanceled' => $this->mirrorSubscription($workspace, $data),
            'subscription.revoked' => $this->handleSubscriptionRevoked($workspace, $data),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function workspaceFromPayload(array $data): ?Workspace
    {
        $publicId = $data['customer']['external_id']
            ?? $data['metadata']['workspace_public_id']
            ?? null;

        if (is_string($publicId) && $publicId !== '') {
            return Workspace::query()->where('public_id', $publicId)->first();
        }

        $customerId = $data['customer_id'] ?? $data['customer']['id'] ?? null;

        return is_string($customerId) && $customerId !== ''
            ? Workspace::query()->where('polar_customer_id', $customerId)->first()
            : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleOrderPaid(Workspace $workspace, array $data): void
    {
        $orderId = (string) ($data['id'] ?? '');
        $productKey = $this->polar->productKey($data['product_id'] ?? $data['product']['id'] ?? null);

        if ($orderId === '' || $productKey === null) {
            Log::warning('Polar order.paid ignored: unknown order or product', ['order' => $orderId]);

            return;
        }

        $isPlus = $productKey === self::PRODUCT_PLUS;
        $credits = $isPlus
            ? (int) config('billing.plans.pro.credits', 100)
            : (int) config("billing.packs.{$productKey}.credits", 0);

        $granted = DB::transaction(function () use ($workspace, $data, $orderId, $productKey, $isPlus, $credits): bool {
            $order = BillingOrder::query()->firstOrCreate(
                ['polar_order_id' => $orderId],
                [
                    'workspace_id' => $workspace->id,
                    'kind' => $isPlus ? BillingOrder::KIND_PLUS : BillingOrder::KIND_PACK,
                    'product_key' => $productKey,
                    'billing_reason' => $data['billing_reason'] ?? null,
                    'credits_granted' => $credits,
                    'amount_cents' => (int) ($data['total_amount'] ?? $data['amount'] ?? 0),
                ],
            );

            // Redelivered webhook: this order already granted its credits.
            if (! $order->wasRecentlyCreated) {
                return false;
            }

            $workspace->forceFill(['polar_customer_id' => $data['customer_id'] ?? $workspace->polar_customer_id])->save();

            if ($isPlus) {
                $wasPlus = $workspace->isPlusActive();
                $workspace->forceFill([
                    'polar_subscription_id' => $data['subscription_id'] ?? $workspace->polar_subscription_id,
                    'polar_subscription_status' => 'active',
                ])->save();
                $this->credits->activatePro($workspace, $data['customer']['email'] ?? null);

                if (! $wasPlus) {
                    $this->extendApiTokens($workspace->fresh() ?? $workspace);
                }
            } else {
                $this->credits->addPurchasedCredits($workspace, $credits);
                $this->rememberEmail($workspace, $data['customer']['email'] ?? null);
            }

            return true;
        });

        if ($granted) {
            Log::info('Polar order granted credits', [
                'workspace' => $workspace->public_id,
                'order' => $orderId,
                'product' => $productKey,
                'credits' => $credits,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleOrderRefunded(Workspace $workspace, array $data): void
    {
        $order = BillingOrder::query()
            ->where('polar_order_id', (string) ($data['id'] ?? ''))
            ->where('workspace_id', $workspace->id)
            ->first();

        if (! $order || $order->refunded_at !== null) {
            return;
        }

        // Partial refunds are a support call; only a full refund claws back a pack.
        $fullyRefunded = ($data['status'] ?? null) === 'refunded';

        DB::transaction(function () use ($workspace, $order, $fullyRefunded): void {
            $order->forceFill(['refunded_at' => now()])->save();

            if ($fullyRefunded && $order->kind === BillingOrder::KIND_PACK) {
                $this->credits->removePurchasedCredits($workspace, $order->credits_granted);
            }
        });
    }

    /**
     * Mirror Polar's subscription state. Plan changes come from order.paid
     * (upgrade) and subscription.revoked (downgrade), not from here.
     *
     * @param  array<string, mixed>  $data
     */
    protected function mirrorSubscription(Workspace $workspace, array $data): void
    {
        if ($this->polar->productKey($data['product_id'] ?? $data['product']['id'] ?? null) !== self::PRODUCT_PLUS) {
            return;
        }

        $periodEnd = $data['current_period_end'] ?? null;

        $workspace->forceFill([
            'polar_customer_id' => $data['customer_id'] ?? $workspace->polar_customer_id,
            'polar_subscription_id' => $data['id'] ?? $workspace->polar_subscription_id,
            'polar_subscription_status' => $data['status'] ?? $workspace->polar_subscription_status,
            'polar_current_period_end' => is_string($periodEnd) ? CarbonImmutable::parse($periodEnd) : $workspace->polar_current_period_end,
            'polar_cancel_at_period_end' => (bool) ($data['cancel_at_period_end'] ?? false),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleSubscriptionRevoked(Workspace $workspace, array $data): void
    {
        // A revoke for some older subscription must not end the current one.
        if ($workspace->polar_subscription_id !== null && ($data['id'] ?? null) !== $workspace->polar_subscription_id) {
            return;
        }

        $workspace->forceFill([
            'polar_subscription_status' => $data['status'] ?? 'canceled',
            'polar_cancel_at_period_end' => false,
        ])->save();

        if ($workspace->normalizedPlan() === Workspace::PLAN_PRO) {
            $this->credits->activateFree($workspace);
            Log::info('Workspace downgraded to Try', ['workspace' => $workspace->public_id]);
        }
    }

    protected function rememberEmail(Workspace $workspace, mixed $email): void
    {
        if (is_string($email) && $email !== '' && blank($workspace->billing_email)) {
            $workspace->forceFill(['billing_email' => $email])->save();
        }
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
