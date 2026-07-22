<?php

namespace App\Providers;

use App\Listeners\SyncWorkspacePlanFromPaddle;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Events\SubscriptionCreated;
use Laravel\Paddle\Events\SubscriptionUpdated;
use Laravel\Paddle\Events\WebhookReceived;

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
        $listener = SyncWorkspacePlanFromPaddle::class;

        Event::listen(WebhookReceived::class, [$listener, 'handleWebhookReceived']);
        Event::listen(SubscriptionCreated::class, [$listener, 'handleSubscriptionCreated']);
        Event::listen(SubscriptionUpdated::class, [$listener, 'handleSubscriptionUpdated']);
    }
}
