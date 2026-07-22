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
        return sprintf(
            '[insufficient_credits] Need %d credit%s; %d remaining this period. Call get_billing, then create_checkout so the human can open Paddle Checkout for Plus ($9/mo, 100 credits).',
            $this->required,
            $this->required === 1 ? '' : 's',
            $this->remaining,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'error' => 'insufficient_credits',
            'message' => $this->getMessage(),
            'required' => $this->required,
            'credits_remaining' => $this->remaining,
            'plan' => $this->workspace->plan,
            'next_action' => 'upgrade',
            'hint' => 'Call get_billing for usage, then create_checkout and open checkout_url for the human.',
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
