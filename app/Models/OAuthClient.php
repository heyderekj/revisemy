<?php

namespace App\Models;

use App\Support\AssistantCallback;
use App\Support\ConnectLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
            $this->logSkip('connect-click');

            return true;
        }

        $uri = $this->requestedRedirectUri();

        $known = $uri !== null
            && auth('web')->check()
            && (string) auth('web')->id() === (string) $user->getAuthIdentifier()
            && AssistantCallback::isKnown($uri);

        if ($known) {
            $this->logSkip('signed-in-browser');
        }

        return $known;
    }

    /**
     * The addresses the code may go back to.
     *
     * Claude Code and other local programs listen on a port picked fresh for
     * each sign-in. The OAuth server already ignores the port for 127.0.0.1
     * and [::1]; this does the same for localhost (RFC 8252 §7.3), so a
     * client registered on one port can sign in from another.
     */
    protected function redirectUris(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes): array {
                $registered = match (true) {
                    ! empty($value) => $this->fromJson($value),
                    ! empty($attributes['redirect']) => explode(',', $attributes['redirect']),
                    default => [],
                };

                $requested = request()->input('redirect_uri');

                if (is_string($requested) && AssistantCallback::matchesLoopbackIgnoringPort($requested, $registered)) {
                    $registered[] = $requested;
                }

                return array_values(array_unique($registered));
            },
        );
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

    private function logSkip(string $via): void
    {
        ConnectLog::event('authorize.skip-consent', [
            'client_id' => (string) $this->getKey(),
            'client' => $this->name,
            'via' => $via,
            'returns_to' => ConnectLog::host($this->requestedRedirectUri()),
        ]);
    }
}
