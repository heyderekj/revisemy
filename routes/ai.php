<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Http\Middleware\RecordAssistantCall;
use App\Http\Middleware\StreamMcpResponse;
use App\Mcp\Servers\ReviseMyServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
 * Two ways in, one door. A try token pasted as a Bearer header (Sanctum), or
 * an assistant that connected by signing in (Passport) — the way Claude,
 * ChatGPT and Grok add a custom connector from just a URL.
 */
$mcpMiddleware = [AuthenticateMcp::class, StreamMcpResponse::class, 'throttle:120,1', RecordAssistantCall::class];

// A host that opens the URL before it POSTs treats an HTML error page as a
// broken connector, so GET and DELETE answer in JSON.
$mcp405 = fn () => response()->json([
    'message' => 'Method not allowed. POST JSON-RPC to this URL.',
], 405, [
    'Allow' => 'POST',
]);

// The -grok path is the same server. Grok caches a failed verdict per URL and
// remove + re-add often does not re-probe, so it gets an address of its own.
foreach (['/mcp/revisemy', '/mcp/revisemy-grok'] as $path) {
    Mcp::web($path, ReviseMyServer::class)->middleware($mcpMiddleware);

    // Registered after Mcp::web(), which adds its own empty text/html 405 for
    // GET and DELETE. The route registered last is the one that answers.
    Route::get($path, $mcp405);
    Route::delete($path, $mcp405);
}

/*
 * Some clients fetch the origin discovery document instead of the path-inserted
 * one. This app has one MCP server, so advertise that resource. Registered
 * before Mcp::oauthRoutes() so Laravel MCP leaves this route alone.
 */
$protectedResource = function (string $path) {
    return response()->json([
        'resource' => url($path),
        'authorization_servers' => [config('mcp.authorization_server') ?? url('/')],
        'scopes_supported' => ['mcp:use'],
    ]);
};

Route::get('/.well-known/oauth-protected-resource', fn () => $protectedResource('/mcp/revisemy'));
Route::get('/.well-known/oauth-protected-resource/mcp/revisemy-grok', fn () => $protectedResource('/mcp/revisemy-grok'));

/*
 * The discovery documents an MCP client reads to find out where to sign in,
 * and dynamic registration so assistants can introduce themselves. Signing in
 * is one click on /connect: no account, just a try workspace of your own.
 */
Mcp::oauthRoutes();
