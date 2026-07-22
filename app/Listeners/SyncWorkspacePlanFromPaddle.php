<?php

namespace App\Listeners;

use App\Models\Workspace;
use App\Services\BillingService;
use Illuminate\Support\Facades\Log;
use Laravel\Paddle\Events\SubscriptionCreated;
use Laravel\Paddle\Events\SubscriptionUpdated;
use Laravel\Paddle\Events\WebhookReceived;

class SyncWorkspacePlanFromPaddle
{
    public function __construct(protected BillingService $billing) {}

    /**
     * Runs before Cashier's built-in handlers — link guest checkout custom_data
     * to the workspace so subscription.created can find the billable.
     */
    public function handleWebhookReceived(WebhookReceived $event): void
    {
        $type = $event->payload['event_type'] ?? null;
        $data = $event->payload['data'] ?? [];

        if (! is_string($type) || ! is_array($data)) {
            return;
        }

        if (! in_array($type, [
            'subscription.created',
            'subscription.updated',
            'transaction.completed',
        ], true)) {
            return;
        }

        $workspace = $this->billing->linkWorkspaceFromPaddlePayload($data);

        if ($workspace) {
            Log::info('Linked workspace to Paddle customer', [
                'workspace' => $workspace->public_id,
                'event' => $type,
            ]);
        }
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $billable = $event->billable;

        if (! $billable instanceof Workspace) {
            return;
        }

        $this->billing->finalizeCheckout($billable, $billable->billing_email);
    }

    public function handleSubscriptionUpdated(SubscriptionUpdated $event): void
    {
        $billable = $event->subscription->billable;

        if (! $billable instanceof Workspace) {
            return;
        }

        $this->billing->syncSubscriptionState($billable);
    }
}
