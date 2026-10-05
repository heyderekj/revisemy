<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grok only ingests tools from Streaming HTTP or SSE. Laravel MCP answers
 * tools/list as JSON, so Connect finished and the tool list was dropped.
 * A successful JSON-RPC body is wrapped as one SSE event when the client
 * asked for event-stream. Errors stay JSON so the OAuth challenge is intact.
 */
class StreamMcpResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('mcp/*') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $accept = (string) $request->header('Accept');

        if (! str_contains($accept, 'text/event-stream')) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type');

        if (! str_contains($type, 'application/json') && ! str_contains($type, 'text/plain')) {
            return $response;
        }

        $body = $response->getContent();

        if ($body === '' || $body === 'null') {
            return $response;
        }

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ];

        if ($session = $response->headers->get('MCP-Session-Id')) {
            $headers['MCP-Session-Id'] = $session;
        }

        return response("data: {$body}\n\n", 200, $headers);
    }
}
