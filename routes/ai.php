<?php

use App\Http\Middleware\AuthenticateMcp;
use App\Http\Middleware\RecordAssistantCall;
use App\Http\Middleware\StreamMcpResponse;
use App\Mcp\Servers\ReviseMyServer;
use App\Support\OAuthMetadata;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

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
foreach (OAuthMetadata::MCP_PATHS as $path) {
    // AuthenticateMcp writes the 401 challenge itself (with scope and
    // invalid_token); the package's own header would overwrite it.
    Mcp::web('/'.$path, ReviseMyServer::class)
        ->middleware($mcpMiddleware)
        ->withoutMiddleware(AddWwwAuthenticateHeader::class);

    // Registered after Mcp::web(), which adds its own empty text/html 405 for
    // GET and DELETE. The route registered last is the one that answers.
    Route::get('/'.$path, $mcp405);
    Route::delete('/'.$path, $mcp405);
}

/*
 * The discovery documents an MCP client reads to find out where to sign in.
 * Registered before Mcp::oauthRoutes(), so these are the ones that answer.
 * Some clients fetch the origin document instead of the path-inserted one;
 * this app has one MCP server, so that one advertises it too.
 */
Route::get('/.well-known/oauth-protected-resource', fn () => OAuthMetadata::json(OAuthMetadata::protectedResource(OAuthMetadata::MCP_PATHS[0])));
Route::get('/.well-known/oauth-authorization-server', fn () => OAuthMetadata::json(OAuthMetadata::authorizationServer()));

foreach (OAuthMetadata::MCP_PATHS as $path) {
    Route::get('/.well-known/oauth-protected-resource/'.$path, fn () => OAuthMetadata::json(OAuthMetadata::protectedResource($path)));
    Route::get('/.well-known/oauth-authorization-server/'.$path, fn () => OAuthMetadata::json(OAuthMetadata::authorizationServer()));
}

/*
 * Dynamic registration, so assistants can introduce themselves, and the
 * generic discovery fallbacks. Signing in is one click on /connect: no
 * account, just a try workspace of your own. routes/oauth.php adds logging
 * and rate limits to registration and the token swap.
 */
Mcp::oauthRoutes();
