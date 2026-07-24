<?php

namespace App\Exceptions;

use App\Models\Workspace;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsufficientCreditsException extends Exception
{
    public function __construct(
        public Workspace $workspace,
        public int $required,
        public int $remaining,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $this->defaultMessage());
    }

    public function defaultMessage(): string
    {
        $grant = (int) config('billing.plans.free.credits', 20);

        if (config('billing.pricing_enabled')) {
            return sprintf(
                '[insufficient_credits] Need %d credit%s; %d remaining. Call create_checkout and immediately paste share_markdown / checkout_url into the human-visible chat for Plus ($9/mo, 100 credits/mo) — do not only say “finish payment in the browser.”',
                $this->required,
                $this->required === 1 ? '' : 's',
                $this->remaining,
            );
        }

        return sprintf(
            '[insufficient_credits] Need %d credit%s; %d remaining. Credits renew monthly (%d/mo) — call get_billing for the refill date. Paid upgrade is paused.',
            $this->required,
            $this->required === 1 ? '' : 's',
            $this->remaining,
            $grant,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $pricingEnabled = (bool) config('billing.pricing_enabled', false);

        return [
            'error' => 'insufficient_credits',
            'message' => $this->getMessage(),
            'required' => $this->required,
            'credits_remaining' => $this->remaining,
            'plan' => $this->workspace->plan,
            'next_action' => $pricingEnabled ? 'upgrade' : 'wait_for_refill',
            'hint' => $pricingEnabled
                ? 'Call create_checkout, then paste share_markdown into chat immediately. Never only say “finish payment in the browser.”'
                : 'Call get_billing for remaining credits and when the monthly pack refills. Paid Plus checkout is paused.',
        ];
    }

    public function render(Request $request): JsonResponse|false
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('mcp/*')) {
            return response()->json($this->payload(), 402);
        }

        return false;
    }
}
