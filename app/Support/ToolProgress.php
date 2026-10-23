<?php

namespace App\Support;

use Fiber;
use Generator;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Lets slow work say what it's doing while a request is still running.
 *
 * A website capture takes 20 to 60 seconds, and hosts showed nothing but a
 * spinner ("Rendering widget") the whole time. When the host asks for
 * progress (a progressToken in the call's _meta), the tool runs its work in a
 * Fiber. Anything along the way can call ToolProgress::report(), which pauses
 * the work just long enough to stream an MCP notifications/progress message,
 * then carries on. The form at /new streams the same steps to its progress
 * dialog through steps(). Without a listener, report() does nothing and the
 * work runs exactly as before.
 */
final class ToolProgress
{
    private static ?Fiber $fiber = null;

    /**
     * Say what's happening now. A no-op unless something is listening.
     * $step is a stable key for a UI to tick off ("open", "shot:mobile-375",
     * "save"); MCP hosts only see the message.
     */
    public static function report(string $message, ?string $step = null): void
    {
        if (self::$fiber !== null && Fiber::getCurrent() === self::$fiber) {
            Fiber::suspend(['message' => $message, 'step' => $step]);
        }
    }

    /**
     * Run $work, yielding each report() as it happens. The work's result is
     * the generator's return value; anything it throws comes out of the loop.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return Generator<int, array{message: string, step: string|null}, mixed, T>
     */
    public static function steps(callable $work): Generator
    {
        $fiber = new Fiber($work);
        $previous = self::$fiber;
        self::$fiber = $fiber;

        try {
            $report = $fiber->start();

            while (! $fiber->isTerminated()) {
                yield $report;

                $report = $fiber->resume();
            }
        } finally {
            self::$fiber = $previous;
        }

        return $fiber->getReturn();
    }

    /**
     * Run $work, streaming each report() as a progress notification, then its result.
     *
     * @param  callable(): (Response|ResponseFactory)  $work
     * @return Generator<int, Response|ResponseFactory>
     */
    public static function stream(string|int $token, callable $work): Generator
    {
        $steps = self::steps($work);
        $step = 0;

        foreach ($steps as $report) {
            yield Response::notification('notifications/progress', [
                'progressToken' => $token,
                'progress' => ++$step,
                'message' => $report['message'],
            ]);
        }

        yield $steps->getReturn();
    }

    /** "mobile-375" → "mobile (375px)". */
    public static function viewport(string $label): string
    {
        [$name, $width] = array_pad(explode('-', $label, 2), 2, null);

        return $width !== null && ctype_digit($width) ? "{$name} ({$width}px)" : $label;
    }

    /** Desktop, mobile and tablet: the viewports' names as a list. */
    public static function viewports(array $labels): string
    {
        $names = array_values(array_unique(array_map(fn (string $label) => explode('-', $label, 2)[0], $labels)));

        return count($names) > 1
            ? implode(', ', array_slice($names, 0, -1)).' and '.end($names)
            : ($names[0] ?? 'the page');
    }
}
