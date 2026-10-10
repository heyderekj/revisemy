<?php

namespace App\Support;

use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;

/**
 * Refresh tokens are single use: each refresh swaps the old one for a new
 * one. If the answer to a refresh is lost (a timeout, a deploy draining, two
 * refreshes racing), the host only has the old token, and it used to be dead
 * on arrival, so Claude showed "Authorization failed" and wanted a reconnect.
 *
 * Now a token swapped in the last minute can be swapped once more. A token
 * revoked any other way, such as Disconnect, has no swap time and stays dead.
 */
class GracefulRefreshTokenRepository extends RefreshTokenRepository
{
    public const GRACE_SECONDS = 60;

    public function revokeRefreshToken(string $tokenId): void
    {
        // Only the first swap starts the clock, so a replay can't extend it.
        Passport::refreshToken()->newQuery()
            ->whereKey($tokenId)
            ->where('revoked', false)
            ->update(['revoked' => true, 'revoked_at' => now()]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return Passport::refreshToken()->newQuery()
            ->whereKey($tokenId)
            ->where(fn ($query) => $query
                ->where('revoked', false)
                ->orWhere('revoked_at', '>=', now()->subSeconds(self::GRACE_SECONDS)))
            ->doesntExist();
    }
}
