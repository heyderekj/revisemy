<?php

namespace App\Providers;

use App\Listeners\SyncWorkspacePlanFromStripe;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Cashier::useCustomerModel(Workspace::class);

        Event::listen(WebhookReceived::class, SyncWorkspacePlanFromStripe::class);
    }
}
