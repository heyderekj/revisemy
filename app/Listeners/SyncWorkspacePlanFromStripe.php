<?php

namespace App\Listeners;

use App\Models\Workspace;
use App\Services\BillingService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class SyncWorkspacePlanFromStripe
{
    public function __construct(protected BillingService $billing) {}

    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! is_string($type)) {
            return;
        }

        if (! in_array($type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'checkout.session.completed',
        ], true)) {
            return;
        }

        $object = $event->payload['data']['object'] ?? [];
        $workspace = $this->resolveWorkspace($type, $object);

        if (! $workspace) {
            Log::warning('Stripe webhook: workspace not found', ['type' => $type]);

            return;
        }

        if ($type === 'checkout.session.completed') {
            $email = $object['customer_details']['email']
                ?? $object['customer_email']
                ?? null;

            if (is_string($email) && $email !== '') {
                $workspace->forceFill(['billing_email' => $email])->save();
            }

            // Cashier may still be creating the subscription row; prefer sync.
            $this->billing->syncSubscriptionState($workspace->fresh() ?? $workspace);

            if ($workspace->fresh()?->normalizedPlan() !== Workspace::PLAN_PRO
                && ($object['mode'] ?? null) === 'subscription'
                && ($object['status'] ?? null) === 'complete') {
                $this->billing->finalizeCheckout($workspace->fresh() ?? $workspace, is_string($email) ? $email : null);
            }

            return;
        }

        $this->billing->syncSubscriptionState($workspace);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function resolveWorkspace(string $type, array $object): ?Workspace
    {
        if ($type === 'checkout.session.completed') {
            $ref = $object['client_reference_id']
                ?? $object['metadata']['workspace_public_id']
                ?? null;

            if (is_string($ref) && $ref !== '') {
                return Workspace::query()->where('public_id', $ref)->first();
            }
        }

        $stripeCustomerId = $object['customer'] ?? null;

        if (is_string($stripeCustomerId) && $stripeCustomerId !== '') {
            return Workspace::query()->where('stripe_id', $stripeCustomerId)->first();
        }

        $meta = $object['metadata']['workspace_public_id'] ?? null;

        if (is_string($meta) && $meta !== '') {
            return Workspace::query()->where('public_id', $meta)->first();
        }

        return null;
    }
}
