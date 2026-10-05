<?php

use App\Http\Middleware\KeepOutOfSearch;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Throwable;

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // MCP clients and the token endpoint need JSON. /oauth/authorize is a
            // browser redirect to Connect, so it must not be forced to JSON.
            fn (Request $request) => $request->is('api/*', 'mcp/*', 'oauth/token', 'oauth/register') || $request->expectsJson(),
        );

        // A missing Passport key pair used to surface as "Server Error" on
        // /oauth/authorize and /oauth/token, so Connect looked broken.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('oauth/*', 'mcp/*')) {
                return null;
            }

            $message = $e->getMessage();

            if (! str_contains($message, 'Invalid key') && ! str_contains($message, 'key file') && ! str_contains($message, 'oauth-private.key') && ! str_contains($message, 'oauth-public.key')) {
                return null;
            }

            $body = 'ReviseMy cannot sign assistants in until PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY are set.';

            if ($request->expectsJson() || $request->is('mcp/*', 'oauth/token', 'oauth/register')) {
                return response()->json(['message' => $body], 503);
            }

            return response($body, 503);
        });

        // A truncated or expired signed image link used to look like the
        // review itself was forbidden. Send them to the review when we can.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if (! $request->is('shots/*')) {
                return null;
            }

            $id = $request->segment(2);

            if (is_numeric($id)) {
                $shot = \App\Models\Screenshot::query()->with('review')->find($id);

                if ($shot?->review?->token) {
                    return redirect('/r/'.$shot->review->token);
                }
            }

            return null;
        });
    })->create();
