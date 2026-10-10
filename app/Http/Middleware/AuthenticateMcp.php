<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ConnectLog;
use App\Support\OAuthMetadata;
use App\Support\PassportKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Sign a caller in, or tell them where to.
 *
 * Custom connectors (Claude, ChatGPT, Grok) probe this URL with no token.
 * They only start OAuth when the answer is 401 and WWW-Authenticate names
 * the protected-resource document.
 *
 * A token that comes back and is turned down gets `error="invalid_token"`,
 * so the host refreshes it, and a log line saying why. On 2026-10-10 every
 * token Claude was given was turned down here without a word, because the
 * public key didn't match the private one, and Claude showed only
 * "Authorization with ReviseMy failed".
 */
class AuthenticateMcp
{
    /** Report broken keys once per process, not on every call. */
    private static bool $reportedKeys = false;

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = (string) $request->bearerToken();
        $user = $this->authenticate($bearer);

        if ($user instanceof User) {
            return $next($request);
        }

        if ($bearer !== '') {
            ConnectLog::event('mcp.rejected', [
                'path' => $request->path(),
                'kind' => $this->looksLikeJwt($bearer) ? 'oauth' : 'try-token',
                'reason' => $this->looksLikeJwt($bearer) ? $this->whyPassportSaidNo($request, $bearer) : 'try token not found or expired',
            ], 'warning');
        }

        return response()->json(['message' => 'Unauthenticated.'], 401)
            ->header('WWW-Authenticate', OAuthMetadata::challenge($request, rejected: $bearer !== ''));
    }

    private function authenticate(string $bearer): ?User
    {
        // A Passport token is a JWT, so a Sanctum lookup can't find it. Skip
        // the query. A try token ("id|secret") is never a JWT, so skip Passport.
        $jwt = $bearer !== '' && $this->looksLikeJwt($bearer);

        if (! $jwt && ($user = $this->viaGuard('sanctum')) !== null) {
            return $user;
        }

        if ($bearer !== '' && ! $jwt) {
            return null;
        }

        // Without a key pair that works, resolving Passport's guard throws.
        if ($jwt && ! PassportKeys::ready()) {
            return null;
        }

        return $this->viaGuard('api');
    }

    private function viaGuard(string $guard): ?User
    {
        try {
            $user = Auth::guard($guard)->user();
        } catch (Throwable $e) {
            // A malformed token is just not signed in. Passport throwing
            // while it reads its keys is ours to fix, so that is reported.
            if ($guard === 'api') {
                $this->reportKeys($e);
            }

            return null;
        }

        if (! $user instanceof User) {
            return null;
        }

        Auth::shouldUse($guard);

        return $user;
    }

    private function looksLikeJwt(string $bearer): bool
    {
        return substr_count($bearer, '.') === 2 && ! str_contains($bearer, '|');
    }

    /**
     * Ask the token checker again, only on a refusal, for the reason it gives.
     * Expired and revoked tokens are routine (the host refreshes); a bad
     * signature means the keys don't match, and is reported.
     */
    private function whyPassportSaidNo(Request $request, string $bearer): string
    {
        if (! PassportKeys::ready()) {
            $this->reportKeys('PASSPORT_PRIVATE_KEY is missing or unreadable.');

            return 'passport private key missing or unreadable';
        }

        try {
            // Passport blanks the Authorization header once it turns a token
            // down, so ask about a copy that still carries it.
            $copy = $request->duplicate();
            $copy->headers->set('Authorization', 'Bearer '.$bearer);
            $psr = (new PsrHttpFactory)->createRequest($copy);

            app(ResourceServer::class)->validateAuthenticatedRequest($psr);

            return 'token checks out, but its workspace user or client is gone';
        } catch (OAuthServerException $e) {
            $violations = $e->getPrevious() instanceof RequiredConstraintsViolated ? $e->getPrevious()->violations() : [];

            // "Could not be verified" covers an expired token too. Only a
            // signature that doesn't check means the keys are wrong.
            foreach ($violations as $violation) {
                if (str_contains((string) $violation->constraint, 'SignedWith')) {
                    $this->reportKeys('a token failed its signature check, so the public key isn’t the private key’s.');
                }
            }

            $why = implode('; ', array_map(fn ($violation) => $violation->getMessage(), $violations)) ?: (string) $e->getHint();

            return ConnectLog::scrub($why !== '' ? $why : $e->getMessage());
        } catch (Throwable $e) {
            $this->reportKeys($e);

            return ConnectLog::reason($e);
        }
    }

    private function reportKeys(Throwable|string $why): void
    {
        if (self::$reportedKeys || $why instanceof OAuthServerException) {
            return;
        }

        self::$reportedKeys = true;

        // Not chained: a key read error can quote the key it failed on.
        report(new RuntimeException('Connector tokens cannot be checked: '.($why instanceof Throwable ? ConnectLog::reason($why) : $why)));
    }
}
