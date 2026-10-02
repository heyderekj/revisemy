<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use App\Services\PolarClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PolarWebhookController extends Controller
{
    public function __invoke(Request $request, PolarClient $polar, BillingService $billing): Response
    {
        $event = $polar->verifyWebhook($request);

        if ($event === null) {
            return response('Invalid signature', 401);
        }

        $billing->handleWebhook($event);

        return response('', 202);
    }
}
