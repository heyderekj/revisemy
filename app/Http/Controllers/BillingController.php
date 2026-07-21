<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Cashier\Cashier;

class BillingController extends Controller
{
    public function success(Request $request, BillingService $billing): View
    {
        $sessionId = (string) $request->query('session_id', '');
        $workspace = null;
        $email = null;

        if ($sessionId !== '' && filled(config('cashier.secret'))) {
            try {
                $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);

                $ref = $session->client_reference_id
                    ?? ($session->metadata['workspace_public_id'] ?? null);

                if (is_string($ref) && $ref !== '') {
                    $workspace = Workspace::query()->where('public_id', $ref)->first();
                }

                $email = $session->customer_details->email
                    ?? $session->customer_email
                    ?? null;

                if ($workspace && ($session->status ?? null) === 'complete') {
                    $billing->finalizeCheckout($workspace, is_string($email) ? $email : null);
                    $workspace = $workspace->fresh();
                }
            } catch (\Throwable) {
                // Show a soft success page anyway — webhooks are the source of truth.
            }
        }

        return view('billing.success', [
            'workspace' => $workspace,
            'email' => $email,
        ]);
    }

    public function cancel(): View
    {
        return view('billing.cancel');
    }

    public function portalReturn(): View
    {
        return view('billing.portal-return');
    }
}
