<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    public function checkout(Request $request, string $workspace, BillingService $billing): View|RedirectResponse
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();

        if ($model->normalizedPlan() === Workspace::PLAN_PRO && $model->subscribed('default')) {
            return redirect()->route('billing.success', ['workspace' => $model->public_id]);
        }

        try {
            $options = $billing->checkoutOpenOptions($model);
        } catch (RuntimeException $e) {
            abort(503, $e->getMessage());
        }

        return view('billing.checkout', [
            'workspace' => $model,
            'options' => $options,
            'priceUsd' => (int) config('billing.plans.pro.price_usd', 9),
            'credits' => (int) config('billing.plans.pro.credits', 100),
        ]);
    }

    public function success(Request $request, BillingService $billing): View
    {
        // Only Paddle checkout sends anyone here, and checkout is off while Plus is paused.
        abort_unless(config('billing.pricing_enabled'), 404);

        $publicId = (string) $request->query('workspace', '');
        $workspace = $publicId !== ''
            ? Workspace::query()->where('public_id', $publicId)->first()
            : null;

        if ($workspace && $workspace->subscribed('default')) {
            $billing->finalizeCheckout($workspace, $workspace->billing_email);
            $workspace = $workspace->fresh();
        }

        $manageUrl = null;
        if ($workspace) {
            try {
                $manageUrl = $billing->createPortalUrl($workspace);
            } catch (\Throwable) {
                $manageUrl = null;
            }
        }

        return view('billing.success', [
            'workspace' => $workspace,
            'email' => $workspace?->billing_email,
            'manageUrl' => $manageUrl,
        ]);
    }

    public function cancel(): View
    {
        abort_unless(config('billing.pricing_enabled'), 404);

        return view('billing.status', [
            'heading' => 'Checkout canceled',
            'line' => 'Nothing was charged. Ask your agent for create_checkout again whenever you’re ready.',
        ]);
    }

    public function manage(Request $request, string $workspace, BillingService $billing): View
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();

        return view('billing.manage', [
            'workspace' => $model,
            'subscribed' => $model->subscribed('default'),
            'status' => $billing->status($model),
        ]);
    }

    public function cancelSubscription(Request $request, string $workspace, BillingService $billing): RedirectResponse
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();

        try {
            $billing->cancelPro($model);
        } catch (\Throwable $e) {
            return redirect()
                ->route('billing.manage', ['workspace' => $model->public_id])
                ->with('error', 'Could not cancel right now — try again or use the link in your Paddle receipt email.');
        }

        return redirect()
            ->route('billing.manage', ['workspace' => $model->public_id])
            ->with('status', 'Plus cancellation scheduled. You’ll keep access until the period ends.');
    }

    /** Reachable whenever someone has a subscription to manage, paused or not. */
    public function portalReturn(): View
    {
        return view('billing.status', [
            'heading' => 'Billing updated',
            'line' => 'You’re all set. Your agent’s get_billing shows the latest plan and credits.',
        ]);
    }
}
