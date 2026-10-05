<?php

namespace App\Support;

/**
 * The ways in, from config/hosts.php `connect`, with the address (and a try
 * token, when there is one) filled in. One list for every surface that says
 * how to connect, so they can't disagree.
 */
final class Hosts
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(?string $token = null): array
    {
        return collect(config('hosts.connect', []))
            ->map(fn (array $host, string $id) => self::fill($id, $host, $token))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $id, ?string $token = null): ?array
    {
        $host = config("hosts.connect.{$id}");

        return is_array($host) ? self::fill($id, $host, $token) : null;
    }

    /** How a host connects, in a few words: the connect list's badge. */
    public static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'oauth' => 'Paste and Connect',
            'deeplink' => 'One click',
            default => 'With a try token',
        };
    }

    public static function mcpUrl(): string
    {
        return url('/mcp/revisemy');
    }

    public static function firstPrompt(): string
    {
        return (string) config('hosts.first_prompt');
    }

    /**
     * @param  array<string, mixed>  $host
     * @return array<string, mixed>
     */
    private static function fill(string $id, array $host, ?string $token): array
    {
        $replace = ['{url}' => self::mcpUrl(), '{token}' => $token ?: 'YOUR_TRY_TOKEN'];

        return [
            'id' => $id,
            'needs_token' => ($host['mode'] ?? null) === 'token',
            'command' => isset($host['command']) ? strtr((string) $host['command'], $replace) : null,
            'prompt' => isset($host['prompt']) ? strtr((string) $host['prompt'], $replace) : null,
        ] + $host + ['steps' => [], 'note' => null, 'inline' => false];
    }
}
