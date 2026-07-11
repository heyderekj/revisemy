<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use PHPUnit\Framework\TestCase;

class DatabaseConfigTest extends TestCase
{
    /**
     * @param  array<string, string|null>  $variables
     */
    private function loadDatabaseConfig(array $variables): array
    {
        $basePath = dirname(__DIR__, 2);
        $app = new Application($basePath);
        Application::setInstance($app);

        $repository = Env::getRepository();

        foreach ($variables as $key => $value) {
            if ($value === null) {
                $repository->clear($key);
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                $repository->set($key, $value);
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return require $basePath.'/config/database.php';
    }

    public function test_cloud_pooler_host_uses_migrate_connection_with_direct_host(): void
    {
        $config = $this->loadDatabaseConfig([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => 'ep-x-pooler.us-east-2.pg.laravel.cloud',
            'DB_URL' => '',
            'DB_MIGRATE_URL' => null,
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'main',
            'DB_USERNAME' => 'cloud-user',
            'DB_PASSWORD' => 'secret',
            'DB_SSLMODE' => null,
            'DB_CONNECT_TIMEOUT' => null,
        ]);

        $this->assertSame('pgsql_migrate', $config['migrations']['connection']);
        $this->assertSame('ep-x.us-east-2.pg.laravel.cloud', $config['connections']['pgsql_migrate']['host']);
        $this->assertSame('require', $config['connections']['pgsql_migrate']['sslmode']);
        $this->assertSame(60, $config['connections']['pgsql_migrate']['connect_timeout']);
        $this->assertStringContainsString('ep-x.us-east-2.pg.laravel.cloud', (string) $config['connections']['pgsql_migrate']['url']);
        $this->assertStringContainsString('sslmode=require', (string) $config['connections']['pgsql_migrate']['url']);
        $this->assertStringContainsString('connect_timeout=60', (string) $config['connections']['pgsql_migrate']['url']);
        $this->assertSame(
            'ep-x-pooler.us-east-2.pg.laravel.cloud',
            $config['connections']['pgsql']['host'],
        );
        $this->assertSame(60, $config['connections']['pgsql']['connect_timeout']);
    }

    public function test_local_pgsql_config_is_unchanged(): void
    {
        $config = $this->loadDatabaseConfig([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_URL' => '',
            'DB_MIGRATE_URL' => null,
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'laravel',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_SSLMODE' => null,
            'DB_CONNECT_TIMEOUT' => null,
        ]);

        $this->assertNull($config['migrations']['connection']);
        $this->assertSame('127.0.0.1', $config['connections']['pgsql_migrate']['host']);
        $this->assertSame('prefer', $config['connections']['pgsql']['sslmode']);
        $this->assertNull($config['connections']['pgsql']['connect_timeout']);
    }

    public function test_explicit_migrate_url_is_hardened_for_serverless(): void
    {
        $config = $this->loadDatabaseConfig([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => '127.0.0.1',
            'DB_URL' => 'postgresql://user:pass@ep-x.neon.tech/main',
            'DB_MIGRATE_URL' => 'postgresql://user:pass@ep-x.neon.tech/main',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'main',
            'DB_USERNAME' => 'user',
            'DB_PASSWORD' => 'pass',
            'DB_SSLMODE' => null,
            'DB_CONNECT_TIMEOUT' => null,
        ]);

        $this->assertSame('pgsql_migrate', $config['migrations']['connection']);
        $this->assertStringContainsString('connect_timeout=60', (string) $config['connections']['pgsql_migrate']['url']);
        $this->assertStringContainsString('connect_timeout=60', (string) $config['connections']['pgsql']['url']);
    }
}
