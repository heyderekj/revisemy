<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sign a caller in, or tell them where to.
 *
 * Custom connectors (Claude, ChatGPT, Grok) probe this URL with no token.
 * They only start OAuth when the answer is 401 and WWW-Authenticate names
 * the protected-resource document. A thrown Passport guard — missing keys,
 * a bad Bearer — used to become a 500, and the connector stopped there.
 */
class AuthenticateMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->authenticate();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401)->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.url('/.well-known/oauth-protected-resource/'.$request->path()).'"'
            );
        }

        return $next($request);
    }

    private function authenticate(): ?User
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if ($user instanceof User) {
                Auth::shouldUse('sanctum');

                return $user;
            }
        } catch (Throwable) {
            // A malformed Sanctum token is just not signed in.
        }

        // Resolving the Passport guard builds a CryptKey. With no key pair
        // that throws, which is what turned the connector probe into a 500.
        if (! $this->passportReady()) {
            return null;
        }

        try {
            $user = Auth::guard('api')->user();

            if ($user instanceof User) {
                Auth::shouldUse('api');

                return $user;
            }
        } catch (Throwable) {
            // An unreadable key or a Bearer that is not ours. Challenge anyway.
        }

        return null;
    }

    private function passportReady(): bool
    {
        return (filled(config('passport.private_key')) || is_file(Passport::keyPath('oauth-private.key')))
            && (filled(config('passport.public_key')) || is_file(Passport::keyPath('oauth-public.key')));
    }
}
