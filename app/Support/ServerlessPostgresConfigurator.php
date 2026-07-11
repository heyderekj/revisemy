<?php

namespace App\Support;

/**
 * Resolve Neon / Laravel Cloud Postgres settings from the live environment.
 *
 * Laravel Cloud runs config:cache during the build image step, before runtime
 * secrets are available. We re-apply database credentials on boot so migrate
 * and web requests use the injected DB_* values.
 */
class ServerlessPostgresConfigurator
{
    public static function apply(): void
    {
        $resolved = self::resolve();

        if ($resolved === null) {
            return;
        }

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => array_merge(
                config('database.connections.pgsql', []),
                $resolved['pgsql'],
            ),
            'database.connections.pgsql_migrate' => array_merge(
                config('database.connections.pgsql_migrate', []),
                $resolved['pgsql_migrate'],
            ),
            'database.migrations.connection' => $resolved['migrations_connection'],
        ]);
    }

    /**
     * @return array{pgsql: array<string, mixed>, pgsql_migrate: array<string, mixed>, migrations_connection: ?string}|null
     */
    public static function resolve(): ?array
    {
        $connection = (string) env('DB_CONNECTION', 'sqlite');

        if ($connection !== 'pgsql') {
            return null;
        }

        $host = PostgresHost::sanitizeHost((string) env('DB_HOST', '127.0.0.1'));
        $url = (string) env('DB_URL', '');

        if (! PostgresHost::isServerlessHost($host) && ! PostgresHost::isServerlessHost($url)) {
            return null;
        }

        $migrateUrl = env('DB_MIGRATE_URL');
        $port = (string) env('DB_PORT', '5432');
        $database = (string) env('DB_DATABASE', 'laravel');
        $username = (string) env('DB_USERNAME', 'root');
        $password = self::passwordFromEnv();
        $sslMode = env('DB_SSLMODE', PostgresHost::defaultSslMode($host, $url));
        $connectTimeout = (int) env('DB_CONNECT_TIMEOUT', 60);
        $isServerless = PostgresHost::isServerlessHost($host) || PostgresHost::isServerlessHost($url);
        $neonEndpointId = $isServerless ? PostgresHost::endpointId($host) : null;
        $useMigrateConnection = PostgresHost::shouldUseMigrateConnection($connection, $host, $url, $migrateUrl);

        $pgsqlConnectionUrl = null;
        if ($isServerless && PostgresHost::isServerlessHost($host)) {
            $pgsqlConnectionUrl = null;
        } elseif ($url !== '') {
            $pgsqlConnectionUrl = $isServerless
                ? PostgresHost::ensureUrlParams($url, $connectTimeout, is_string($sslMode) ? $sslMode : 'require')
                : $url;
        }

        $pgsqlMigrateConnectionUrl = null;
        if ($migrateUrl !== null && $migrateUrl !== '') {
            $pgsqlMigrateConnectionUrl = $isServerless
                ? PostgresHost::ensureUrlParams($migrateUrl, $connectTimeout, is_string($sslMode) ? $sslMode : 'require')
                : $migrateUrl;
        }

        return [
            'pgsql' => [
                'url' => $pgsqlConnectionUrl,
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'sslmode' => $sslMode,
                'connect_timeout' => $isServerless ? $connectTimeout : null,
                'neon_endpoint' => $neonEndpointId,
            ],
            'pgsql_migrate' => [
                'url' => $pgsqlMigrateConnectionUrl,
                'host' => PostgresHost::directHost($host),
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
                'sslmode' => is_string($sslMode) ? $sslMode : 'require',
                'connect_timeout' => $connectTimeout,
                'neon_endpoint' => $neonEndpointId,
            ],
            'migrations_connection' => $useMigrateConnection ? 'pgsql_migrate' : null,
        ];
    }

    public static function passwordFromEnv(): string
    {
        $password = (string) env('DB_PASSWORD', '');

        return trim($password, " \t\n\r\0\x0B\"'");
    }
}
