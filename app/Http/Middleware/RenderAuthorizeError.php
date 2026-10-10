<?php

namespace App\Http\Middleware;

use App\Support\AssistantCallback;
use App\Support\Hosts;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A person, not a program, opens /oauth/authorize. When the sign-in link is
 * bad (an assistant registration we don't have, a return address it never
 * registered, a missing PKCE challenge), Passport answers in JSON, and the
 * person saw {"error":"invalid_client",…} in their browser. They get a page
 * that says what to do instead. Errors the assistant can handle itself go
 * back to it as a redirect and never reach this.
 */
class RenderAuthorizeError
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() < 400 || $request->expectsJson()
            || ! str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return $response;
        }

        $error = json_decode((string) $response->getContent(), true)['error'] ?? null;

        if (! is_string($error)) {
            return $response;
        }

        $redirectUri = $request->query('redirect_uri');
        $assistant = AssistantCallback::identify(is_string($redirectUri) ? $redirectUri : null);

        return response()->view('oauth.error', [
            'error' => $error,
            'assistant' => $assistant !== null && ! $assistant['local'] ? $assistant['name'] : null,
            'mcpUrl' => Hosts::mcpUrl(),
        ], $response->getStatusCode());
    }
}
