<?php

namespace App\Support;

/**
 * Whether a sign-in's return address belongs to an assistant we know.
 *
 * Any app can register itself as a connector, so a browser that has connected
 * before only skips the consent screen when the code is going back to Claude,
 * ChatGPT, Grok, VS Code, Cursor or a program on this machine. Everything else
 * is asked, so a stranger's client never gets a silent token.
 */
class AssistantCallback
{
    /** Loopback hosts may use any port, the same rule the OAuth server applies. */
    protected const LOOPBACK = ['127.0.0.1', '[::1]', 'localhost'];

    public static function isKnown(string $uri): bool
    {
        $parts = parse_url($uri);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ($host === '') {
            return false;
        }

        // A desktop app's own scheme only ever opens that app.
        if (in_array($scheme, (array) config('mcp.custom_schemes', []), true)) {
            return true;
        }

        if ($scheme === 'http') {
            return in_array($host, self::LOOPBACK, true);
        }

        if ($scheme !== 'https') {
            return false;
        }

        foreach ((array) config('revisemy.oauth.trusted_redirect_hosts', []) as $trusted) {
            $trusted = strtolower((string) $trusted);

            if ($host === $trusted || str_ends_with($host, '.'.$trusted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $requested is one of the $registered loopback addresses on a
     * different port. Only http on localhost, 127.0.0.1 or [::1], and the
     * path and query must be the same; nothing else is loosened.
     *
     * @param  array<int, string>  $registered
     */
    public static function matchesLoopbackIgnoringPort(string $requested, array $registered): bool
    {
        $want = self::loopbackWithoutPort($requested);

        if ($want === null) {
            return false;
        }

        foreach ($registered as $uri) {
            if (is_string($uri) && self::loopbackWithoutPort($uri) === $want) {
                return true;
            }
        }

        return false;
    }

    /** "http://localhost:3118/callback" → "http://localhost/callback"; null for anything not loopback. */
    private static function loopbackWithoutPort(string $uri): ?string
    {
        $parts = parse_url($uri);

        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');

        if (strtolower($parts['scheme'] ?? '') !== 'http' || ! in_array($host, self::LOOPBACK, true)) {
            return null;
        }

        return 'http://'.$host.($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
