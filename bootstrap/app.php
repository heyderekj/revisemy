<?php

use App\Http\Middleware\KeepOutOfSearch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'noindex' => KeepOutOfSearch::class,
        ]);
        // Polar signs its webhooks (Standard Webhooks); there is no session to forge.
        $middleware->preventRequestForgery(except: ['polar/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // MCP clients need the 401 (with WWW-Authenticate) to learn where
            // to sign in, never a redirect to the Connect page.
            fn (Request $request) => $request->is('api/*', 'mcp/*') || $request->expectsJson(),
        );
    })->create();
