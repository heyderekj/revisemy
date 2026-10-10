<?php

namespace App\Http\Middleware;

use App\Support\ConnectLog;
use App\Support\OAuthMetadata;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A log line for each OAuth step an assistant takes: registering, sending
 * the person to sign in, swapping a code or refresh token. Status, OAuth
 * error and timing, never the code, token or verifier.
 */
class LogOAuthResponse
{
    public function handle(Request $request, Closure $next, string $step): Response
    {
        $started = hrtime(true);
        $response = $next($request);
        $status = $response->getStatusCode();
        $body = $this->json($response);
        $resource = $request->input('resource');

        ConnectLog::event('oauth.'.$step, [
            'status' => $status,
            'grant' => $step === 'token' ? $request->input('grant_type') : null,
            'client_id' => $body['client_id'] ?? $request->input('client_id'),
            'client' => $step === 'register' ? Str::limit((string) $request->input('client_name'), 60) : null,
            'returns_to' => ConnectLog::host($step === 'register'
                ? Arr::first((array) $request->input('redirect_uris'))
                : $request->input('redirect_uri')),
            'goes_to' => $this->location($response),
            // RFC 8707: logged, never refused, so a host that spells it
            // differently still connects.
            'resource_known' => is_string($resource) ? $this->knownResource($resource) : null,
            'error' => $body['error'] ?? $this->locationError($response),
            'error_description' => isset($body['error_description']) ? Str::limit((string) $body['error_description'], 160) : null,
            'hint' => isset($body['hint']) ? Str::limit((string) $body['hint'], 160) : null,
            'ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ], $status >= 400 ? 'warning' : 'info');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        if (! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return [];
        }

        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Where a redirect sends the browser, without its query (which can hold the code). */
    private function location(Response $response): ?string
    {
        $location = $response->headers->get('Location');

        if (! is_string($location) || $location === '') {
            return null;
        }

        return ConnectLog::host($location).(parse_url($location, PHP_URL_PATH) ?: '');
    }

    /** An authorize error goes back to the assistant as ?error=… on its callback. */
    private function locationError(Response $response): ?string
    {
        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        return isset($query['error']) && is_string($query['error']) ? $query['error'] : null;
    }

    private function knownResource(string $resource): bool
    {
        $resource = rtrim(strtolower($resource), '/');

        foreach (OAuthMetadata::MCP_PATHS as $path) {
            if ($resource === strtolower(url('/'.$path))) {
                return true;
            }
        }

        return false;
    }
}
