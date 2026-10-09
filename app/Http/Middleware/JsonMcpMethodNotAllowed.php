<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel MCP registers GET /mcp/* as an empty 405. That response is
 * text/html, and a host that opens the URL treats the connector as broken.
 * It is not an exception, so the exception renderer never sees it; this
 * rewrites it as JSON on the way out.
 */
class JsonMcpMethodNotAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('mcp/*') && $response->getStatusCode() === 405) {
            return response()->json([
                'message' => 'Method not allowed. POST JSON-RPC to this URL.',
            ], 405, [
                'Allow' => 'POST',
            ]);
        }

        return $response;
    }
}
