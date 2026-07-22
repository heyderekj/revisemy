<?php

use App\Http\Controllers\AlternativeController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ScreenshotController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\UseCaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/for', [UseCaseController::class, 'index']);
Route::get('/for/{slug}', [UseCaseController::class, 'show'])
    ->where('slug', '[a-z]+');

Route::get('/connectors', [GuideController::class, 'show'])
    ->defaults('slug', 'connectors');
Route::get('/second-opinion', [GuideController::class, 'show'])
    ->defaults('slug', 'second-opinion');
Route::get('/board', [GuideController::class, 'show'])
    ->defaults('slug', 'board');
Route::get('/guest-links', [GuideController::class, 'show'])
    ->defaults('slug', 'guest-links');
Route::get('/webhooks', [GuideController::class, 'show'])
    ->defaults('slug', 'webhooks');
Route::get('/mcp-apps', [GuideController::class, 'show'])
    ->defaults('slug', 'mcp-apps');
Route::get('/changelog', [GuideController::class, 'show'])
    ->defaults('slug', 'changelog');

Route::get('/privacy', [LegalController::class, 'privacy']);
Route::get('/terms', [LegalController::class, 'terms']);

Route::get('/upgrade', [BillingController::class, 'upgrade'])->name('billing.upgrade');
Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
Route::get('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
Route::get('/billing/portal-return', [BillingController::class, 'portalReturn'])->name('billing.portal-return');
Route::get('/billing/checkout/{workspace}', [BillingController::class, 'checkout'])
    ->middleware('signed')
    ->name('billing.checkout');
Route::get('/billing/manage/{workspace}', [BillingController::class, 'manage'])
    ->middleware('signed')
    ->name('billing.manage');
Route::post('/billing/manage/{workspace}/cancel', [BillingController::class, 'cancelSubscription'])
    ->middleware('signed')
    ->name('billing.cancel-subscription');

Route::get('/alternatives', [AlternativeController::class, 'index']);
Route::get('/alternatives/{slug}', [AlternativeController::class, 'show'])
    ->where('slug', '[a-z0-9-]+');

Route::get('/llms.txt', [SeoController::class, 'llms']);
Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

Route::get('/r/{token}', function (string $token) {
    return view('review', ['token' => $token]);
})->name('reviews.show');

Route::get('/r/{token}/board', function (string $token) {
    return view('review-board', ['token' => $token]);
})->name('reviews.board');

Route::get('/shots/{screenshot}', [ScreenshotController::class, 'show'])
    ->middleware('signed')
    ->name('screenshots.show');

Route::get('/shots/{screenshot}/thumb', [ScreenshotController::class, 'thumb'])
    ->middleware('signed')
    ->name('screenshots.thumb');
