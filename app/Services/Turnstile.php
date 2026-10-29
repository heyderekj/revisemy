<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile in front of a new try workspace made from the site.
 * Off until both keys are set, so local and tests need nothing. When
 * Cloudflare can't be reached the check lets the person through: the per-IP
 * limits in TryTokenGate still hold, and /new shouldn't go down with it.
 */
class Turnstile
{
    public const FIELD = 'cf-turnstile-response';

    public const MESSAGE = 'We couldn’t confirm this is a person. Try again.';

    public function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    public function passes(Request $request): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $token = (string) $request->input(self::FIELD, '');

        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile unreachable, letting the request through', ['error' => $e->getMessage()]);

            return true;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
