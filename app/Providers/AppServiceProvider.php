<?php

namespace App\Providers;

use App\Models\OAuthClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
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
        // Discovery advertises this scope. An undefined scope makes some hosts
        // finish Connect and then drop the tool list.
        Passport::tokensCan([
            'mcp:use' => 'Create and read ReviseMy reviews',
        ]);

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
