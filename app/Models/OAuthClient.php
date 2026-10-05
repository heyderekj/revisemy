<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

/**
 * An assistant that registered itself to connect over OAuth.
 *
 * The Connect page is the consent: whoever clicked Connect there has already
 * said yes, and a browser that is still signed in can reconnect without
 * Passport's one-time approve token. That token was failing after a connector
 * was removed and rendering the 403 page.
 */
class OAuthClient extends Client
{
    public const CONNECTED_KEY = 'connect.approved';

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        $approved = session()->pull(self::CONNECTED_KEY);

        if (is_array($approved)
            && ($approved['client_id'] ?? null) === (string) $this->getKey()
            && ($approved['user_id'] ?? null) === $user->getAuthIdentifier()
            && now()->timestamp - (int) ($approved['at'] ?? 0) < 300) {
            return true;
        }

        // Re-adding a connector reuses this browser's remembered sign-in.
        // Passport's consent form then fails its one-time auth token and
        // rendered the 403 page. The Connect click already was the consent.
        return auth('web')->check()
            && (string) auth('web')->id() === (string) $user->getAuthIdentifier();
    }
}
