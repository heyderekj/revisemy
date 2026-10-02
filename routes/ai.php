<?php

use App\Mcp\Servers\ReviseMyServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * Two ways in, one door. A try token pasted as a Bearer header (Sanctum), or
 * an assistant that connected by signing in (Passport) — the way Claude.ai,
 * Claude Desktop and ChatGPT add a custom connector from just a URL.
 */
Mcp::web('/mcp/revisemy', ReviseMyServer::class)
    ->middleware(['auth:sanctum,api', 'throttle:120,1']);

/*
 * The discovery documents an MCP client reads to find out where to sign in,
 * and dynamic registration so assistants can introduce themselves. Signing in
 * is one click on /connect: no account, just a try workspace of your own.
 */
Mcp::oauthRoutes();
