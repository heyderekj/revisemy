<?php

namespace App\Support;

use Fiber;
use Generator;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Lets slow work say what it's doing while an MCP tool call is still running.
 *
 * A website capture takes 20 to 60 seconds, and hosts showed nothing but a
 * spinner ("Rendering widget") the whole time. When the host asks for
 * progress (a progressToken in the call's _meta), the tool runs its work in a
 * Fiber. Anything along the way can call ToolProgress::report(), which pauses
 * the work just long enough to stream an MCP notifications/progress message,
 * then carries on. Without a token, or outside a tool call, report() does
 * nothing and the work runs exactly as before.
 */
final class ToolProgress
{
    private static ?Fiber $fiber = null;

    /** Say what's happening now. A no-op unless a host is listening. */
    public static function report(string $message): void
    {
        if (self::$fiber !== null && Fiber::getCurrent() === self::$fiber) {
            Fiber::suspend($message);
        }
    }

    /**
     * Run $work, streaming each report() as a progress notification, then its result.
     *
     * @param  callable(): (Response|ResponseFactory)  $work
     * @return Generator<int, Response|ResponseFactory>
     */
    public static function stream(string|int $token, callable $work): Generator
    {
        $fiber = new Fiber($work);
        $previous = self::$fiber;
        self::$fiber = $fiber;

        try {
            $message = $fiber->start();
            $step = 0;

            while (! $fiber->isTerminated()) {
                yield Response::notification('notifications/progress', [
                    'progressToken' => $token,
                    'progress' => ++$step,
                    'message' => (string) $message,
                ]);

                $message = $fiber->resume();
            }
        } finally {
            self::$fiber = $previous;
        }

        yield $fiber->getReturn();
    }

    /** "mobile-375" → "mobile (375px)". */
    public static function viewport(string $label): string
    {
        [$name, $width] = array_pad(explode('-', $label, 2), 2, null);

        return $width !== null && ctype_digit($width) ? "{$name} ({$width}px)" : $label;
    }
}
