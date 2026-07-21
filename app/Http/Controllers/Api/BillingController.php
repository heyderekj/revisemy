<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends Controller
{
    public function show(Request $request, BillingService $billing): JsonResponse
    {
        return response()->json($billing->status($request->user()->workspace));
    }

    public function checkout(Request $request, BillingService $billing): JsonResponse
    {
        try {
            $url = $billing->createCheckoutUrl($request->user()->workspace);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'checkout_url' => $url,
            'plan' => 'pro',
            'price_usd' => (int) config('billing.plans.pro.price_usd', 9),
            'credits_grant' => (int) config('billing.plans.pro.credits', 100),
            'next_action' => 'open_checkout',
        ]);
    }

    public function portal(Request $request, BillingService $billing): JsonResponse
    {
        try {
            $url = $billing->createPortalUrl($request->user()->workspace);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'portal_url' => $url,
            'next_action' => 'open_portal',
        ]);
    }
}
