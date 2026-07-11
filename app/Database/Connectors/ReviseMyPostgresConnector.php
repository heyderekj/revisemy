<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Adds connect_timeout to the PDO DSN so Neon cold starts can outlive short defaults.
 */
class ReviseMyPostgresConnector extends PostgresConnector
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function addSslOptions($dsn, array $config)
    {
        $dsn = parent::addSslOptions($dsn, $config);

        if (isset($config['connect_timeout'])) {
            $dsn .= ';connect_timeout='.(int) $config['connect_timeout'];
        }

        return $dsn;
    }
}
