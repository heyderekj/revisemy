<?php

use App\Support\PostgresHost;
use Illuminate\Support\Str;
use Pdo\Mysql;

$dbConnection = (string) env('DB_CONNECTION', 'sqlite');
$dbHost = PostgresHost::sanitizeHost((string) env('DB_HOST', '127.0.0.1'));
$dbUrl = (string) env('DB_URL', '');
$dbMigrateUrl = env('DB_MIGRATE_URL');
$dbPort = (string) env('DB_PORT', '5432');
$dbDatabase = (string) env('DB_DATABASE', 'laravel');
$dbUsername = (string) env('DB_USERNAME', 'root');
$dbPassword = (string) env('DB_PASSWORD', '');
$dbSslMode = env('DB_SSLMODE', PostgresHost::defaultSslMode($dbHost, $dbUrl));
$dbConnectTimeout = (int) env('DB_CONNECT_TIMEOUT', 60);
$isServerless = PostgresHost::isServerlessHost($dbHost) || PostgresHost::isServerlessHost($dbUrl);
$neonEndpointId = $isServerless ? PostgresHost::endpointId($dbHost) : null;
// Neon recommends embedding the endpoint id in the password for Laravel/PDO clients
// without SNI: https://neon.tech/docs/connect/connection-errors#d-specify-the-endpoint-id-in-the-password-field
$dbPasswordForServerless = PostgresHost::passwordForServerless($dbPassword, $neonEndpointId);
$useMigrateConnection = PostgresHost::shouldUseMigrateConnection($dbConnection, $dbHost, $dbUrl, $dbMigrateUrl);

$migrateUrl = $dbMigrateUrl;

if ($migrateUrl !== null && $migrateUrl !== '' && $isServerless) {
    $migrateUrl = PostgresHost::ensureUrlParams($migrateUrl, $dbConnectTimeout, is_string($dbSslMode) ? $dbSslMode : 'require');
}

$runtimePgsqlUrl = $dbUrl;

if ($runtimePgsqlUrl !== '' && $isServerless) {
    $runtimePgsqlUrl = PostgresHost::ensureUrlParams($runtimePgsqlUrl, $dbConnectTimeout, is_string($dbSslMode) ? $dbSslMode : 'require');
}

$pgsqlConnectionUrl = null;
if ($isServerless && PostgresHost::isServerlessHost($dbHost)) {
    $pgsqlConnectionUrl = null;
} elseif ($runtimePgsqlUrl !== '') {
    $pgsqlConnectionUrl = $runtimePgsqlUrl;
} else {
    $pgsqlConnectionUrl = env('DB_URL') ?: null;
}

$pgsqlMigrateConnectionUrl = ($dbMigrateUrl !== null && $dbMigrateUrl !== '') ? $migrateUrl : null;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => $dbConnection,

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => $pgsqlConnectionUrl,
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbDatabase,
            'username' => $dbUsername,
            'password' => $dbPasswordForServerless,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => $dbSslMode,
            'connect_timeout' => $isServerless ? $dbConnectTimeout : null,
        ],

        // Neon / Laravel Cloud Serverless Postgres: migrations use the direct host
        // (no -pooler) with a longer connect_timeout for cold-start wake-ups.
        'pgsql_migrate' => [
            'driver' => 'pgsql',
            'url' => $pgsqlMigrateConnectionUrl,
            'host' => PostgresHost::directHost($dbHost),
            'port' => $dbPort,
            'database' => $dbDatabase,
            'username' => $dbUsername,
            'password' => $dbPasswordForServerless,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => is_string($dbSslMode) ? $dbSslMode : 'require',
            'connect_timeout' => $dbConnectTimeout,
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
        'connection' => $useMigrateConnection ? 'pgsql_migrate' : null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
