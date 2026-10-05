<?php

namespace App\Http\Controllers;

use App\Models\OAuthClient;
use App\Models\User;
use App\Services\TryTokenGate;
use App\Services\TryTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

/**
 * Connecting an assistant by signing in, without an account.
 *
 * Claude, ChatGPT and Grok add a custom connector from a URL and
 * sign in over OAuth; they can't send a pasted Bearer token. Passport sends
 * them here to "log in", and there is nothing to log in to — so this page is
 * one button. Connect makes a try workspace, the same as Get a try token,
 * and signs this browser in as it just long enough to hand the assistant its
 * token. Someone who already has a try token can paste it to connect to that
 * workspace instead, so their reviews show up in both places.
 */
class ConnectController extends Controller
{
    public function show(Request $request): View
    {
        return view('connect', [
            'client' => $this->pendingClient($request),
            'returnsTo' => $this->returnsTo($request),
        ]);
    }

    public function store(Request $request, TryTokenService $tryTokens, TryTokenGate $gate): RedirectResponse
    {
        $client = $this->pendingClient($request);

        if (! $client) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        if (filled($data['token'] ?? null)) {
            $user = $this->userForToken($data['token']);

            if (! $user) {
                return back()->withErrors(['token' => 'That try token isn’t valid any more. Leave it empty to start a new workspace.']);
            }
        } else {
            try {
                $gate->assertCanMint($request);
            } catch (RuntimeException $e) {
                return back()->withErrors(['token' => $e->getMessage()]);
            }

            $user = $tryTokens->createWorkspaceUser();
        }

        // Remembered, so the homepage and /reviews still know this browser on a later visit.
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put(OAuthClient::CONNECTED_KEY, [
            'client_id' => (string) $client->getKey(),
            'user_id' => $user->getKey(),
            'at' => now()->timestamp,
        ]);

        return redirect()->intended('/');
    }

    /**
     * The client whose sign-in brought this browser here, read back from the
     * authorize URL Passport saved before sending it to log in.
     */
    protected function pendingClient(Request $request): ?OAuthClient
    {
        $query = $this->intendedQuery($request);
        $id = $query['client_id'] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        return OAuthClient::query()->whereKey($id)->where('revoked', false)->first();
    }

    /**
     * Any app can register itself and call itself anything, so the page names
     * where saying yes sends you: a lookalike "Claude" shows its real address.
     */
    protected function returnsTo(Request $request): ?string
    {
        $uri = $this->intendedQuery($request)['redirect_uri'] ?? null;

        return is_string($uri) ? (parse_url($uri, PHP_URL_HOST) ?: $uri) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function intendedQuery(Request $request): array
    {
        $intended = (string) $request->session()->get('url.intended', '');

        if (! str_contains($intended, '/oauth/authorize')) {
            return [];
        }

        parse_str((string) parse_url($intended, PHP_URL_QUERY), $query);

        return $query;
    }

    protected function userForToken(string $plain): ?User
    {
        $token = PersonalAccessToken::findToken(trim($plain));

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }

        $user = $token->tokenable;

        return $user instanceof User && $user->workspace ? $user : null;
    }
}
