<?php

use App\Http\Middleware\LogOAuthResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

/*
 * The OAuth steps an assistant takes, loaded after Passport and Laravel MCP
 * register theirs (bootstrap/app.php, `then:`), so these win. Same
 * controllers; what changes is a log line per step and rate limits keyed to
 * the assistant rather than the address: every Claude user's token swap
 * comes from Anthropic's servers, which share a handful of IPs.
 */
Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware(['web', LogOAuthResponse::class.':authorize'])
    ->name('passport.authorizations.authorize');

Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware(['throttle:oauth-token', LogOAuthResponse::class.':token'])
    ->name('passport.token');

Route::post('/oauth/register', OAuthRegisterController::class)
    ->middleware(['throttle:oauth-register', LogOAuthResponse::class.':register'])
    ->name('mcp.oauth.register');
