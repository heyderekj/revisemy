<?php

namespace Tests\Unit;

use App\Database\Connectors\ReviseMyPostgresConnector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ReviseMyPostgresConnectorTest extends TestCase
{
    public function test_adds_connect_timeout_to_dsn(): void
    {
        $connector = new ReviseMyPostgresConnector;
        $method = new ReflectionMethod($connector, 'addSslOptions');

        $dsn = $method->invoke($connector, 'pgsql:host=ep-x.neon.tech;dbname=main', [
            'sslmode' => 'require',
            'connect_timeout' => 60,
        ]);

        $this->assertStringContainsString('sslmode=require', $dsn);
        $this->assertStringContainsString('connect_timeout=60', $dsn);
    }
}
