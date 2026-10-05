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
Route::get('/mcp/revisemy', function () {
    return response('', 405, [
        'Allow' => 'POST',
        'Content-Type' => 'application/json',
    ]);
});

Mcp::web('/mcp/revisemy', ReviseMyServer::class)
    ->middleware([AuthenticateMcp::class, StreamMcpResponse::class, 'throttle:120,1', RecordAssistantCall::class]);

/*
 * Some clients fetch the origin discovery document instead of the path-inserted
 * one. This app has one MCP server, so advertise that resource. Registered
 * before Mcp::oauthRoutes() so Laravel MCP leaves this route alone.
 */
Route::get('/.well-known/oauth-protected-resource', function () {
    return response()->json([
        'resource' => url('/mcp/revisemy'),
        'authorization_servers' => [config('mcp.authorization_server') ?? url('/')],
        'scopes_supported' => ['mcp:use'],
    ]);
});

/*
 * The discovery documents an MCP client reads to find out where to sign in,
 * and dynamic registration so assistants can introduce themselves. Signing in
 * is one click on /connect: no account, just a try workspace of your own.
 */
Mcp::oauthRoutes();
