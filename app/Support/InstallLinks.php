<?php

namespace App\Support;

/**
 * One-click installs for the editors that take a deep link.
 *
 * With a try token the link carries it as a Bearer header; without one the
 * editor connects by signing in (OAuth), the way Claude.ai does, and lands on
 * /connect. Same server either way.
 */
final class InstallLinks
{
    public const NAME = 'revisemy';

    /**
     * @return array{cursor: string, vscode: string, claude_code: string}
     */
    public static function for(?string $token = null): array
    {
        $url = url('/mcp/revisemy');
        $headers = filled($token) ? ['Authorization' => 'Bearer '.$token] : null;

        $cursor = array_filter(['url' => $url, 'headers' => $headers]);
        $vscode = array_filter(['name' => self::NAME, 'type' => 'http', 'url' => $url, 'headers' => $headers]);

        return [
            'cursor' => 'cursor://anysphere.cursor-deeplink/mcp/install?'.http_build_query([
                'name' => self::NAME,
                'config' => base64_encode((string) json_encode($cursor, JSON_UNESCAPED_SLASHES)),
            ]),
            'vscode' => 'vscode:mcp/install?'.rawurlencode((string) json_encode($vscode, JSON_UNESCAPED_SLASHES)),
            'claude_code' => filled($token)
                ? sprintf('claude mcp add --transport http %s %s --header "Authorization: Bearer %s"', self::NAME, $url, $token)
                : sprintf('claude mcp add --transport http %s %s', self::NAME, $url),
        ];
    }
}
