<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * One log line per step of an assistant connecting: registered, sent to sign
 * in, handed a token, turned away by the MCP URL. Claude's error only says
 * "Authorization failed", so this is where we find out which step it was.
 *
 * Never a code, token, verifier, secret or key: only which client, where it
 * returns to, what happened and how long it took.
 */
class ConnectLog
{
    /** Keys that must never reach a log line, whatever a caller passes. */
    private const SECRET_KEYS = [
        'code', 'code_verifier', 'code_challenge', 'access_token', 'refresh_token',
        'token', 'client_secret', 'password', 'auth_token', 'state', 'authorization',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public static function event(string $step, array $context = [], string $level = 'info'): void
    {
        $request = request();

        $context = array_filter([
            ...array_diff_key($context, array_flip(self::SECRET_KEYS)),
            'ip' => $request->ip(),
            'agent' => self::agent((string) $request->userAgent()),
        ], fn ($value) => $value !== null && $value !== '');

        try {
            // On Laravel Cloud, write to its own channel so these lines show
            // under Monitoring → Logs even if LOG_CHANNEL points elsewhere.
            $channel = config()->has('logging.channels.laravel-cloud-socket') ? 'laravel-cloud-socket' : null;

            Log::channel($channel)->log($level, 'connect.'.$step, $context);
        } catch (Throwable) {
            // Logging must never be why a sign-in fails.
        }
    }

    /** A redirect address reduced to where it goes, never its query. */
    public static function host(?string $uri): ?string
    {
        if (! is_string($uri) || $uri === '') {
            return null;
        }

        $parts = parse_url($uri);

        if ($parts === false) {
            return null;
        }

        $host = ($parts['scheme'] ?? '').'://'.($parts['host'] ?? '');

        return isset($parts['port']) ? $host.':'.$parts['port'] : $host;
    }

    /** An exception's class and message, with anything shaped like key material cut out. */
    public static function reason(Throwable $e): string
    {
        return class_basename($e).': '.self::scrub($e->getMessage());
    }

    public static function scrub(string $message): string
    {
        $message = (string) preg_replace('/-----BEGIN [^-]+-----.*?(-----END [^-]+-----|$)/s', '[key]', $message);
        $message = (string) preg_replace('/[A-Za-z0-9+\/=_\-.]{40,}/', '[redacted]', $message);

        return Str::limit($message, 160);
    }

    /** "Claude-User/1.0 (+https://…)" → "Claude-User/1.0". */
    private static function agent(string $agent): ?string
    {
        $agent = trim(strtok($agent, ' ') ?: '');

        return $agent === '' ? null : Str::limit($agent, 60, '');
    }
}
