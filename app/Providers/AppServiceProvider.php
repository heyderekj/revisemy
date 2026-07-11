<?php

namespace App\Providers;

use App\Database\Connectors\ReviseMyPostgresConnector;
use App\Support\ServerlessPostgresConfigurator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            'db.connector.pgsql',
            ReviseMyPostgresConnector::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ServerlessPostgresConfigurator::apply();
    }
}
