<?php

namespace App\Support;

/**
 * Neon / Laravel Cloud Serverless Postgres host helpers.
 *
 * Runtime may use the pooled (-pooler) endpoint; migrations need the direct host.
 */
class PostgresHost
{
    public static function isServerlessHost(?string $hostOrUrl): bool
    {
        if ($hostOrUrl === null || $hostOrUrl === '') {
            return false;
        }

        return str_contains($hostOrUrl, 'neon.tech')
            || str_contains($hostOrUrl, 'pg.laravel.cloud')
            || str_contains($hostOrUrl, '-pooler');
    }

    public static function isPooledHost(?string $hostOrUrl): bool
    {
        return $hostOrUrl !== null && $hostOrUrl !== '' && str_contains($hostOrUrl, '-pooler');
    }

    public static function directHost(string $host): string
    {
        return str_replace('-pooler', '', $host);
    }

    public static function defaultSslMode(?string $host, ?string $url): string
    {
        if (self::isServerlessHost($host) || self::isServerlessHost($url)) {
            return 'require';
        }

        return 'prefer';
    }

    public static function shouldUseMigrateConnection(
        string $defaultConnection,
        ?string $host,
        ?string $url,
        ?string $migrateUrl,
    ): bool {
        if ($defaultConnection !== 'pgsql') {
            return false;
        }

        if ($migrateUrl !== null && $migrateUrl !== '') {
            return true;
        }

        return self::isPooledHost($host) || self::isPooledHost($url);
    }

    /**
     * Build a libpq URL with SSL and a patient connect timeout for cold starts.
     */
    public static function buildUrl(
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
        string $sslmode = 'require',
        int $connectTimeout = 60,
    ): string {
        $user = rawurlencode($username);
        $pass = rawurlencode($password);
        $host = self::directHost($host);
        $path = '/'.ltrim($database, '/');
        $query = http_build_query([
            'sslmode' => $sslmode,
            'connect_timeout' => $connectTimeout,
        ]);

        return "postgresql://{$user}:{$pass}@{$host}:{$port}{$path}?{$query}";
    }

    /**
     * Ensure serverless-friendly query params exist on an existing Postgres URL.
     */
    public static function ensureUrlParams(
        string $url,
        int $connectTimeout = 60,
        string $sslmode = 'require',
    ): string {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);
        $query['sslmode'] ??= $sslmode;
        $query['connect_timeout'] ??= (string) $connectTimeout;

        $parts['query'] = http_build_query($query);

        return self::unparseUrl($parts);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function unparseUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user.$pass.'@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return "{$scheme}{$auth}{$host}{$port}{$path}{$query}";
    }
}
