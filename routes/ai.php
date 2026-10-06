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
 *
 * GET must be an empty 405, not an HTML error page. Laravel's default empty
 * response is text/html, and a host that opens the URL then drops the tools.
 */
$mcpMiddleware = [AuthenticateMcp::class, StreamMcpResponse::class, 'throttle:120,1', RecordAssistantCall::class];

// GET must be JSON, not an HTML error page. A host that opens the URL
// before POST treats text/html as a broken connector.
$mcpGet = function () {
    return response()->json([
        'message' => 'Method not allowed. POST JSON-RPC to this URL.',
    ], 405, [
        'Allow' => 'POST',
    ]);
};

Route::get('/mcp/revisemy', $mcpGet);
Route::get('/mcp/revisemy-grok', $mcpGet);

Mcp::web('/mcp/revisemy', ReviseMyServer::class)->middleware($mcpMiddleware);

// Same server, different path. Grok caches a failed verdict per URL and
// remove + re-add often does not re-probe. Paste this one after a deploy.
Mcp::web('/mcp/revisemy-grok', ReviseMyServer::class)->middleware($mcpMiddleware);

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
