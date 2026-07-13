<?php

use App\Http\Controllers\ScreenshotController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

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
