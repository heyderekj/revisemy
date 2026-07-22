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
        $publicId = (string) $request->query('workspace', '');
        $workspace = $publicId !== ''
            ? Workspace::query()->where('public_id', $publicId)->first()
            : null;

        if ($workspace && $workspace->subscribed('default')) {
            $billing->finalizeCheckout($workspace, $workspace->billing_email);
            $workspace = $workspace->fresh();
        }

        return view('billing.success', [
            'workspace' => $workspace,
            'email' => $workspace?->billing_email,
        ]);
    }

    public function cancel(): View
    {
        return view('billing.cancel');
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
            ->with('status', 'Pro cancellation scheduled. You’ll keep access until the period ends.');
    }

    public function portalReturn(): View
    {
        return view('billing.portal-return');
    }
}
