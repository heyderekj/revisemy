<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grok only ingests tools from Streaming HTTP or SSE. Laravel MCP answers
 * tools/list as JSON, so Connect finished and the tool list was dropped.
 *
 * Every spec-compliant host sends text/event-stream in Accept, so asking for
 * it is not a signal. Only the -grok path, or Grok itself on the main path,
 * gets a successful JSON-RPC body wrapped as one SSE event. Everyone else
 * keeps the JSON the package returns. Errors stay JSON so the OAuth challenge
 * is intact.
 */
class StreamMcpResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->wantsStream($request) || $response->getStatusCode() !== 200) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type');

        if (! str_contains($type, 'application/json') && ! str_contains($type, 'text/plain')) {
            return $response;
        }

        $body = str_replace(["\r", "\n"], '', $response->getContent());

        if ($body === '' || $body === 'null') {
            return $response;
        }

        // MCP streamable HTTP requires the event name. A bare data line is
        // ignored by hosts that only keep SSE, which is how Connect stuck
        // without create_review.
        $stream = response("event: message\ndata: {$body}\n\n", 200);

        // Keep everything the JSON response carried (session id, rate limit)
        // except the headers that describe the old body.
        foreach ($response->headers->allPreserveCase() as $name => $values) {
            if (in_array(strtolower($name), ['content-type', 'content-length'], true)) {
                continue;
            }

            $stream->headers->set($name, $values);
        }

        $stream->headers->set('Content-Type', 'text/event-stream');
        $stream->headers->set('Cache-Control', 'no-cache');
        $stream->headers->set('X-Accel-Buffering', 'no');

        return $stream;
    }

    protected function wantsStream(Request $request): bool
    {
        if (! str_contains((string) $request->header('Accept'), 'text/event-stream')) {
            return false;
        }

        return $request->is('mcp/revisemy-grok')
            || preg_match('/grok|xai/i', (string) $request->userAgent()) === 1;
    }
}
