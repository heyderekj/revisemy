<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\CreditsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    /**
     * Signed link from create_checkout → a fresh Polar checkout session.
     */
    public function checkout(Request $request, string $workspace, BillingService $billing): View|RedirectResponse|Response
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();
        $product = (string) $request->query('product', BillingService::PRODUCT_PLUS);

        if ($product === BillingService::PRODUCT_PLUS && $model->isPlusActive()) {
            return view('billing.success', [
                'workspace' => $model,
                'kind' => 'already_plus',
            ]);
        }

        try {
            $url = $billing->startCheckout($model, $product);
        } catch (RuntimeException $e) {
            // The person gets a plain "not right now"; the reason ([pricing_disabled],
            // Polar's own rejection, …) goes to the log for whoever runs the server.
            Log::warning('Checkout unavailable', [
                'workspace' => $model->public_id,
                'product' => $product,
                'reason' => $e->getMessage(),
            ]);

            return response()->view('billing.unavailable', [], 503);
        }

        return redirect()->away($url);
    }

    /**
     * Polar's return page. Display only: credits are granted by the order.paid
     * webhook, so reloading this page can never grant anything.
     */
    public function success(): View
    {
        return view('billing.success', ['workspace' => null, 'kind' => 'paid']);
    }

    public function cancel(): View
    {
        return view('billing.cancel');
    }

    /**
     * Public pricing page.
     */
    public function upgrade(CreditsService $credits): View
    {
        abort_unless(config('billing.pricing_enabled'), 404);

        return view('billing.upgrade', [
            'tryCredits' => (int) config('billing.plans.free.credits', 20),
            'priceUsd' => (int) config('billing.plans.pro.price_usd', 9),
            'credits' => (int) config('billing.plans.pro.credits', 100),
            'packs' => $credits->packs(),
        ]);
    }

    public function manage(Request $request, string $workspace, BillingService $billing): View
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();

        return view('billing.manage', [
            'workspace' => $model,
            'subscribed' => $model->isPlusActive(),
            'status' => $billing->status($model),
            'packUrls' => $this->packUrls($model, $billing),
        ]);
    }

    /**
     * Mint a short-lived Polar customer-portal session on click.
     */
    public function portal(Request $request, string $workspace, BillingService $billing): RedirectResponse
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();

        try {
            return redirect()->away($billing->polarPortalUrl($model));
        } catch (RuntimeException) {
            return back()->with('error', 'Could not open billing right now — try again in a moment.');
        }
    }

    public function cancelSubscription(Request $request, string $workspace, BillingService $billing): RedirectResponse
    {
        $model = Workspace::query()->where('public_id', $workspace)->firstOrFail();

        try {
            $billing->cancelPro($model);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not cancel right now — try again, or cancel from the Polar receipt email.');
        }

        return back()->with('status', 'Plus cancellation scheduled. You’ll keep access until the period ends.');
    }

    /**
     * @return array<string, string>
     */
    protected function packUrls(Workspace $workspace, BillingService $billing): array
    {
        $urls = [];

        foreach (array_keys((array) config('billing.packs', [])) as $key) {
            try {
                $urls[$key] = $billing->createCheckoutUrl($workspace, $key);
            } catch (RuntimeException) {
                // Pack not configured on this host — just don't offer it.
            }
        }

        return $urls;
    }
}
