<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Support\BrandAssets;
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
        $product = (string) $request->input('product', BillingService::PRODUCT_PLUS);

        try {
            $url = $billing->createCheckoutUrl($request->user()->workspace, $product);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'checkout_url' => $url,
            'share_markdown' => BrandAssets::markdownShareLink($url),
            ...$billing->checkoutDetails($product),
            'next_action' => 'share_checkout_url',
            'hint' => 'Paste share_markdown into the human-visible chat immediately. Do not only say “finish payment in the browser.”',
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
            'share_markdown' => BrandAssets::markdownShareLink($url),
            'next_action' => 'share_portal_url',
            'hint' => 'Paste share_markdown into the human-visible chat immediately. Do not only say “open the billing page.”',
        ]);
    }
}
