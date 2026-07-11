<?php

namespace Tests\Unit;

use App\Support\PostgresHost;
use PHPUnit\Framework\TestCase;

class PostgresHostTest extends TestCase
{
    public function test_detects_serverless_neon_host(): void
    {
        $this->assertTrue(PostgresHost::isServerlessHost('ep-abc.us-east-1.aws.neon.tech'));
    }

    public function test_detects_serverless_laravel_cloud_host(): void
    {
        $this->assertTrue(PostgresHost::isServerlessHost('ep-abc.us-east-2.pg.laravel.cloud'));
    }

    public function test_detects_serverless_pooler_host(): void
    {
        $this->assertTrue(PostgresHost::isServerlessHost('ep-abc-pooler.us-east-2.pg.laravel.cloud'));
    }

    public function test_local_host_is_not_serverless(): void
    {
        $this->assertFalse(PostgresHost::isServerlessHost('127.0.0.1'));
        $this->assertFalse(PostgresHost::isServerlessHost('postgres'));
        $this->assertFalse(PostgresHost::isServerlessHost(null));
        $this->assertFalse(PostgresHost::isServerlessHost(''));
    }

    public function test_direct_host_strips_pooler_and_direct_suffixes(): void
    {
        $this->assertSame(
            'ep-abc.us-east-2.pg.laravel.cloud',
            PostgresHost::directHost('ep-abc-pooler.us-east-2.pg.laravel.cloud'),
        );
        $this->assertSame(
            'ep-abc.us-east-2.pg.laravel.cloud',
            PostgresHost::directHost('ep-abc-direct.us-east-2.pg.laravel.cloud'),
        );
    }

    public function test_sanitize_host_strips_query_string(): void
    {
        $this->assertSame(
            'ep-abc-direct.us-east-2.pg.laravel.cloud',
            PostgresHost::sanitizeHost('ep-abc-direct.us-east-2.pg.laravel.cloud?options=endpoint%3Dep-abc'),
        );
    }

    public function test_endpoint_id_from_laravel_cloud_host(): void
    {
        $this->assertSame(
            'ep-misty-smoke-aiihk586',
            PostgresHost::endpointId('ep-misty-smoke-aiihk586.c-4.aws-us-east-1.pg.laravel.cloud'),
        );
    }

    public function test_password_for_serverless_prefixes_neon_endpoint(): void
    {
        $this->assertSame(
            'endpoint=ep-abc$secret',
            PostgresHost::passwordForServerless('secret', 'ep-abc'),
        );
        $this->assertSame(
            'endpoint=ep-abc$secret',
            PostgresHost::passwordForServerless('endpoint=ep-abc$secret', 'ep-abc'),
        );
    }

    public function test_should_use_migrate_connection_for_any_serverless_host(): void
    {
        $this->assertTrue(
            PostgresHost::shouldUseMigrateConnection(
                'pgsql',
                'ep-misty-smoke-aiihk586.c-4.aws-us-east-1.pg.laravel.cloud',
                '',
                null,
            ),
        );
    }

    public function test_direct_host_leaves_non_pooler_unchanged(): void
    {
        $this->assertSame('127.0.0.1', PostgresHost::directHost('127.0.0.1'));
        $this->assertSame(
            'ep-abc.us-east-2.pg.laravel.cloud',
            PostgresHost::directHost('ep-abc.us-east-2.pg.laravel.cloud'),
        );
    }

    public function test_default_ssl_mode_requires_serverless_hosts(): void
    {
        $this->assertSame(
            'require',
            PostgresHost::defaultSslMode('ep-abc-pooler.us-east-2.pg.laravel.cloud', ''),
        );
        $this->assertSame(
            'require',
            PostgresHost::defaultSslMode('', 'postgresql://user@ep-abc.neon.tech/db'),
        );
    }

    public function test_default_ssl_mode_prefers_local_hosts(): void
    {
        $this->assertSame('prefer', PostgresHost::defaultSslMode('127.0.0.1', ''));
        $this->assertSame('prefer', PostgresHost::defaultSslMode('postgres', ''));
    }

    public function test_should_use_migrate_connection_for_cloud_pooler_host(): void
    {
        $host = 'ep-x-pooler.us-east-2.pg.laravel.cloud';

        $this->assertTrue(
            PostgresHost::shouldUseMigrateConnection('pgsql', $host, '', null),
        );
    }

    public function test_should_use_migrate_connection_when_migrate_url_is_set(): void
    {
        $this->assertTrue(
            PostgresHost::shouldUseMigrateConnection(
                'pgsql',
                '127.0.0.1',
                '',
                'postgresql://user:pass@127.0.0.1:5432/db',
            ),
        );
    }

    public function test_should_not_use_migrate_connection_for_sqlite(): void
    {
        $this->assertFalse(
            PostgresHost::shouldUseMigrateConnection(
                'sqlite',
                'ep-x-pooler.us-east-2.pg.laravel.cloud',
                '',
                null,
            ),
        );
    }

    public function test_should_not_use_migrate_connection_for_local_pgsql(): void
    {
        $this->assertFalse(
            PostgresHost::shouldUseMigrateConnection('pgsql', '127.0.0.1', '', null),
        );
    }

    public function test_build_url_uses_direct_host_and_serverless_params(): void
    {
        $url = PostgresHost::buildUrl(
            'ep-x-pooler.us-east-2.pg.laravel.cloud',
            '5432',
            'main',
            'cloud-user',
            's3cret',
        );

        $this->assertStringContainsString('ep-x.us-east-2.pg.laravel.cloud', $url);
        $this->assertStringNotContainsString('-pooler', $url);
        $this->assertStringContainsString('sslmode=require', $url);
        $this->assertStringContainsString('connect_timeout=60', $url);
        $this->assertStringContainsString('endpoint%3Dep-x%24', $url);
        $this->assertStringContainsString('cloud-user', $url);
        $this->assertStringContainsString('main', $url);
    }

    public function test_ensure_url_params_adds_missing_serverless_query_params(): void
    {
        $url = PostgresHost::ensureUrlParams(
            'postgresql://user:pass@ep-x.neon.tech/main',
        );

        $this->assertStringContainsString('sslmode=require', $url);
        $this->assertStringContainsString('connect_timeout=60', $url);
    }

    public function test_ensure_url_params_preserves_existing_query_params(): void
    {
        $url = PostgresHost::ensureUrlParams(
            'postgresql://user:pass@ep-x.neon.tech/main?sslmode=require&connect_timeout=90',
            60,
            'require',
        );

        $this->assertStringContainsString('sslmode=require', $url);
        $this->assertStringContainsString('connect_timeout=90', $url);
    }
}
