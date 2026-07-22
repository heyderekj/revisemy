<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class TryTokenGate
{
    public const MESSAGE = 'Try limit reached for today — come back tomorrow, or upgrade with create_checkout.';

    public function assertCanMint(Request $request): void
    {
        $ip = $request->ip() ?: 'unknown';
        $hourKey = $this->hourKey($ip);
        $dayKey = $this->dayKey($ip);
        $perHour = max(1, (int) config('billing.try_token.per_hour', 3));
        $perDay = max(1, (int) config('billing.try_token.per_day', 3));

        if (RateLimiter::tooManyAttempts($hourKey, $perHour) || RateLimiter::tooManyAttempts($dayKey, $perDay)) {
            throw new RuntimeException(self::MESSAGE);
        }

        RateLimiter::hit($hourKey, 3600);
        RateLimiter::hit($dayKey, 86400);
    }

    public function hourKey(string $ip): string
    {
        return 'try-token:hour:'.$ip;
    }

    public function dayKey(string $ip): string
    {
        return 'try-token:day:'.$ip;
    }
}
