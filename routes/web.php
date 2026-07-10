<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/r/{token}', function (string $token) {
    return view('review', ['token' => $token]);
})->name('reviews.show');
