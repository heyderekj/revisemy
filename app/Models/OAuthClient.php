<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

/**
 * An assistant that registered itself to connect over OAuth.
 *
 * The Connect page is the consent: whoever clicked Connect there has already
 * said yes to this client, so Passport's own "Authorize?" page is skipped for
 * it, once, within a few minutes. A later sign-in from the same browser still
 * asks (resources/views/oauth/authorize.blade.php).
 */
class OAuthClient extends Client
{
    public const CONNECTED_KEY = 'connect.approved';

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        $approved = session()->pull(self::CONNECTED_KEY);

        return is_array($approved)
            && ($approved['client_id'] ?? null) === (string) $this->getKey()
            && ($approved['user_id'] ?? null) === $user->getAuthIdentifier()
            && now()->timestamp - (int) ($approved['at'] ?? 0) < 300;
    }
}
