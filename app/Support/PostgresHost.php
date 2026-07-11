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

        $hostOrUrl = self::sanitizeHost($hostOrUrl);

        return str_contains($hostOrUrl, 'neon.tech')
            || str_contains($hostOrUrl, 'pg.laravel.cloud')
            || str_contains($hostOrUrl, '-pooler')
            || str_contains($hostOrUrl, '-direct');
    }

    public static function isPooledHost(?string $hostOrUrl): bool
    {
        return $hostOrUrl !== null && $hostOrUrl !== '' && str_contains(self::sanitizeHost($hostOrUrl), '-pooler');
    }

    /**
     * Strip accidental URL query strings from DB_HOST and Neon role suffixes.
     */
    public static function sanitizeHost(string $host): string
    {
        $host = trim($host);

        if (($queryPosition = strpos($host, '?')) !== false) {
            $host = substr($host, 0, $queryPosition);
        }

        return $host;
    }

    public static function directHost(string $host): string
    {
        return str_replace(['-pooler', '-direct'], '', self::sanitizeHost($host));
    }

    /**
     * Neon / Laravel Cloud endpoint id (e.g. ep-misty-smoke-aiihk586).
     */
    public static function endpointId(string $host): ?string
    {
        $host = self::sanitizeHost($host);

        if (! preg_match('/^(ep-[^.]+)/', $host, $matches)) {
            return null;
        }

        $endpointId = self::directHost($matches[1]);

        return str_starts_with($endpointId, 'ep-') ? $endpointId : null;
    }

    /**
     * Neon SNI workaround for libpq without endpoint-aware SNI (Laravel Cloud containers).
     *
     * @see https://neon.tech/docs/connect/connection-errors#the-endpoint-id-is-not-specified
     */
    public static function passwordForServerless(string $password, ?string $endpointId): string
    {
        if ($endpointId === null || $endpointId === '' || str_contains($password, 'endpoint=')) {
            return $password;
        }

        return "endpoint={$endpointId}\${$password}";
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

        return self::isPooledHost($host) || self::isPooledHost($url) || self::isServerlessHost($host);
    }

    /**
     * libpq "options" DSN value for Neon endpoint routing.
     */
    public static function endpointOptions(?string $endpointId): ?string
    {
        if ($endpointId === null || $endpointId === '') {
            return null;
        }

        return 'endpoint='.$endpointId;
    }

    /**
     * Build a libpq URL with SSL, connect timeout, and Neon endpoint routing.
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
        $query = [
            'sslmode' => $sslmode,
            'connect_timeout' => $connectTimeout,
        ];

        $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return "postgresql://{$user}:{$pass}@{$host}:{$port}{$path}?{$queryString}";
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
        unset($query['options']);

        $parts['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

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
