<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    /** Who a return address belongs to: https host (or a subdomain of it), or a desktop app's scheme. */
    protected const ASSISTANTS = [
        'claude.ai' => ['Claude', 'claude'],
        'claude.com' => ['Claude', 'claude'],
        'chatgpt.com' => ['ChatGPT', 'chatgpt'],
        'grok.com' => ['Grok', 'grok'],
        'x.ai' => ['Grok', 'grok'],
        'vscode.dev' => ['VS Code', 'vscode'],
        'cursor://' => ['Cursor', 'cursor'],
        'vscode://' => ['VS Code', 'vscode'],
        'vscode-insiders://' => ['VS Code', 'vscode'],
    ];

    /**
     * The assistant a sign-in really returns to, worked out from the return
     * address and never from the name a client registered: anyone can
     * register as "Claude", but only Claude receives codes at claude.ai.
     *
     * @return array{name: string, icon: ?string, local: bool}|null
     */
    public static function identify(?string $uri): ?array
    {
        $parts = is_string($uri) ? parse_url($uri) : false;

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (isset(self::ASSISTANTS[$scheme.'://']) && $host !== '') {
            [$name, $icon] = self::ASSISTANTS[$scheme.'://'];

            return ['name' => $name, 'icon' => $icon, 'local' => false];
        }

        if ($scheme === 'http' && in_array($host, self::LOOPBACK, true)) {
            return ['name' => 'A program on this computer', 'icon' => null, 'local' => true];
        }

        if ($scheme !== 'https') {
            return null;
        }

        foreach (self::ASSISTANTS as $known => [$name, $icon]) {
            if (! str_ends_with($known, '://') && ($host === $known || str_ends_with($host, '.'.$known))) {
                return ['name' => $name, 'icon' => $icon, 'local' => false];
            }
        }

        return null;
    }

    /**
     * The name to show for a client: the assistant its return address belongs
     * to, or else the name it registered, kept short.
     */
    public static function displayName(string $clientName, ?string $uri): string
    {
        $assistant = self::identify($uri);

        if ($assistant !== null && ! $assistant['local']) {
            return $assistant['name'];
        }

        // A lookalike goes by where it really sends you, so the button never
        // says "Connect Claude" for an app that isn't.
        if (self::impersonates($clientName, $uri)) {
            return (string) (parse_url((string) $uri, PHP_URL_HOST) ?: 'this app');
        }

        return Str::limit(trim($clientName) ?: 'your assistant', 40);
    }

    /**
     * Whether a client's chosen name claims to be an assistant that the
     * return address says it isn't, e.g. "Claude" sending codes to evil.example.
     */
    public static function impersonates(string $clientName, ?string $uri): bool
    {
        // A known address (or a program on this computer, like Claude Code)
        // is whoever it says it is.
        if (self::identify($uri) !== null) {
            return false;
        }

        $claimed = strtolower($clientName);

        foreach (array_unique(array_column(self::ASSISTANTS, 0)) as $name) {
            if (str_contains($claimed, strtolower($name))) {
                return true;
            }
        }

        return false;
    }

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
