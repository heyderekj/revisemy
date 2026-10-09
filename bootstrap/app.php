<?php

use App\Http\Middleware\KeepOutOfSearch;
use App\Models\Screenshot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

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

        // A GET of the MCP URL used to render an HTML error page. Hosts that
        // open the address before POST then treat the connector as broken.
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('mcp/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Method not allowed. POST JSON-RPC to this URL.',
            ], 405, [
                'Allow' => 'POST',
            ]);
        });
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
            $id = $request->segment(2);

            // Only a truncated or expired signed link. An unsigned /shots/{id}
            // must stay 403 — redirecting it would reveal the review token.
            if ($request->is('shots/*') && $request->has('signature') && is_numeric($id)) {
                $shot = Screenshot::query()->with('review')->find($id);

                if ($shot?->review?->token) {
                    return redirect('/r/'.$shot->review->token);
                }
            }

            return response()->view('errors.403', [], 403);
        });

        // Reconnecting a removed connector reuses this browser. Passport rejects
        // the old approve token, and other authorize failures, as a 403. The
        // human should land on Connect, never the error page.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('oauth/*') || $request->expectsJson() || $request->is('oauth/token', 'oauth/register')) {
                return null;
            }

            $forbidden = $e instanceof AuthorizationException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403);

            if (! $forbidden) {
                return null;
            }

            if (! $request->session()->has('url.intended') && $request->is('oauth/authorize')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('login')->withErrors([
                'token' => 'That connect attempt expired. Click Connect here, then start it again from the assistant if it does not return.',
            ]);
        });
    })->create();
