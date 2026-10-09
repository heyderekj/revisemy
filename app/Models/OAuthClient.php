<?php

namespace App\Models;

use App\Support\AssistantCallback;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

/**
 * An assistant that registered itself to connect over OAuth.
 *
 * The Connect page is the consent: whoever clicked Connect there has already
 * said yes to that client, once. After that a browser that is still signed in
 * reconnects without Passport's one-time approve token, which was failing
 * after a connector was removed and rendering the 403 page, but only when the
 * code goes back to an assistant we know. Registration is open, so any other
 * return address always sees the consent screen.
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

        $uri = $this->requestedRedirectUri();

        return $uri !== null
            && auth('web')->check()
            && (string) auth('web')->id() === (string) $user->getAuthIdentifier()
            && AssistantCallback::isKnown($uri);
    }

    /**
     * Where this sign-in sends the code. The OAuth server has already checked
     * it against the client's registered addresses before asking us; with no
     * redirect_uri in the request, a client with one address uses that one.
     */
    protected function requestedRedirectUri(): ?string
    {
        $uri = request()->query('redirect_uri');

        if (is_string($uri) && $uri !== '') {
            return $uri;
        }

        $registered = $this->redirect_uris;

        return count($registered) === 1 ? (string) $registered[0] : null;
    }
}
