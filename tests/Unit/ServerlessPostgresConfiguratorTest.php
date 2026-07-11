<?php

namespace Tests\Unit;

use App\Support\ServerlessPostgresConfigurator;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use PHPUnit\Framework\TestCase;

class ServerlessPostgresConfiguratorTest extends TestCase
{
    /**
     * @param  array<string, string|null>  $variables
     */
    private function withEnv(array $variables): void
    {
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
    }

    public function test_apply_refreshes_cached_credentials_from_runtime_env(): void
    {
        $basePath = dirname(__DIR__, 2);
        $app = new Application($basePath);
        Application::setInstance($app);

        $config = new Repository([
            'database' => [
                'default' => 'pgsql',
                'connections' => [
                    'pgsql' => [
                        'driver' => 'pgsql',
                        'host' => 'ep-misty-smoke-aiihk586.c-4.aws-us-east-1.pg.laravel.cloud',
                        'port' => '5432',
                        'database' => 'main',
                        'username' => 'laravel',
                        'password' => '',
                        'sslmode' => 'require',
                        'neon_endpoint' => 'ep-misty-smoke-aiihk586',
                    ],
                    'pgsql_migrate' => [
                        'driver' => 'pgsql',
                        'host' => 'ep-misty-smoke-aiihk586.c-4.aws-us-east-1.pg.laravel.cloud',
                        'port' => '5432',
                        'database' => 'main',
                        'username' => 'laravel',
                        'password' => '',
                        'sslmode' => 'require',
                        'neon_endpoint' => 'ep-misty-smoke-aiihk586',
                    ],
                ],
                'migrations' => [
                    'connection' => 'pgsql_migrate',
                ],
            ],
        ]);

        $app->instance('config', $config);

        $this->withEnv([
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => 'ep-misty-smoke-aiihk586.c-4.aws-us-east-1.pg.laravel.cloud',
            'DB_PORT' => '5432',
            'DB_DATABASE' => 'main',
            'DB_USERNAME' => 'laravel',
            'DB_PASSWORD' => 'cloud-secret',
            'DB_SSLMODE' => 'require',
            'DB_URL' => null,
            'DB_MIGRATE_URL' => null,
        ]);

        ServerlessPostgresConfigurator::apply();

        $this->assertSame('cloud-secret', $config->get('database.connections.pgsql.password'));
        $this->assertSame('cloud-secret', $config->get('database.connections.pgsql_migrate.password'));
        $this->assertSame('laravel', $config->get('database.connections.pgsql.username'));
    }
}
