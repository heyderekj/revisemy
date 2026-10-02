<?php

namespace App\Providers;

use App\Listeners\SyncWorkspacePlanFromPaddle;
use App\Models\OAuthClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Paddle\Events\SubscriptionCreated;
use Laravel\Paddle\Events\SubscriptionUpdated;
use Laravel\Paddle\Events\WebhookReceived;
use Laravel\Passport\Passport;

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
        // Assistants that connect by signing in (routes/ai.php). The consent
        // page is ReviseMy's own, and the first Connect skips it entirely.
        Passport::useClientModel(OAuthClient::class);
        Passport::authorizationView('oauth.authorize');
        // An hour, then a refresh: a stolen token is a short-lived one, and
        // every client that can do OAuth refreshes without asking again.
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(60));

        $listener = SyncWorkspacePlanFromPaddle::class;

        Event::listen(WebhookReceived::class, [$listener, 'handleWebhookReceived']);
        Event::listen(SubscriptionCreated::class, [$listener, 'handleSubscriptionCreated']);
        Event::listen(SubscriptionUpdated::class, [$listener, 'handleSubscriptionUpdated']);
    }
}
