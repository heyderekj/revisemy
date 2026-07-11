<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/r/{token}', function (string $token) {
    return view('review', ['token' => $token]);
})->name('reviews.show');

Route::get('/r/{token}/board', function (string $token) {
    return view('review-board', ['token' => $token]);
})->name('reviews.board');
