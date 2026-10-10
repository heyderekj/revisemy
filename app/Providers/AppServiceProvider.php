<?php

namespace App\Providers;

use App\Models\OAuthClient;
use App\Support\GracefulRefreshTokenRepository;
use App\Support\PassportKeys;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passport checks tokens with whatever public key it's given. Give it
        // the one that matches the private key, so the pair can't drift.
        $this->app->beforeResolving(ResourceServer::class, fn () => PassportKeys::apply());

        // A refresh token that was just swapped still works for a minute, so
        // a host that lost the answer can ask again instead of reconnecting.
        $this->app->bind(RefreshTokenRepository::class, GracefulRefreshTokenRepository::class);
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

        // Every Claude user's token swap comes from Anthropic's servers, and
        // ChatGPT's from OpenAI's, so a per-address limit would be shared by
        // all of them. Limit each assistant instead, with a high ceiling per
        // address as the backstop.
        RateLimiter::for('oauth-token', fn (Request $request) => [
            Limit::perMinute(30)->by('oauth-token:client:'.$request->input('client_id', 'none')),
            Limit::perMinute(600)->by('oauth-token:ip:'.$request->ip()),
        ]);
        RateLimiter::for('oauth-register', fn (Request $request) => Limit::perMinute(120)->by('oauth-register:'.$request->ip()));

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
