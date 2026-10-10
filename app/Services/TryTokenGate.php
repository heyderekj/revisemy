<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class TryTokenGate
{
    public const MESSAGE = 'Try limit reached for today — come back tomorrow.';

    public const HOUR_MESSAGE = 'Too many new try workspaces from this network in the last hour. Try again in a little while, or paste a try token you already have.';

    public const DAY_MESSAGE = 'Too many new try workspaces from this network today. Come back tomorrow, or paste a try token you already have.';

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

    /**
     * A new workspace from Connect, the sign-in page an assistant sends you to.
     *
     * Its own allowance, apart from Get a try token: Connect takes a
     * registered assistant and a round trip through the browser, and an
     * office or a family shares one address. A browser that's already
     * connected never gets here; it reuses its workspace.
     */
    public function assertCanConnect(Request $request): void
    {
        $ip = $request->ip() ?: 'unknown';
        $hourKey = 'try-connect:hour:'.$ip;
        $dayKey = 'try-connect:day:'.$ip;

        if (RateLimiter::tooManyAttempts($hourKey, max(1, (int) config('billing.try_token.connect_per_hour', 6)))) {
            throw new RuntimeException(self::HOUR_MESSAGE);
        }

        if (RateLimiter::tooManyAttempts($dayKey, max(1, (int) config('billing.try_token.connect_per_day', 20)))) {
            throw new RuntimeException(self::DAY_MESSAGE);
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
