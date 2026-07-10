<?php

use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TryTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/try-token', [TryTokenController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/{publicId}', [ReviewController::class, 'show']);
    Route::post('/reviews/{publicId}/screenshots', [ReviewController::class, 'addScreenshot']);
    Route::post('/reviews/{publicId}/findings', [ReviewController::class, 'addFindings']);
    Route::post('/reviews/{publicId}/second-opinion', [ReviewController::class, 'requestSecondOpinion']);
});
