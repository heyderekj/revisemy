<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plans & credit grants
    |--------------------------------------------------------------------------
    |
    | Try (internal key `free`) is a one-time credit pack — no monthly refill.
    | Plus (internal key `pro`) renews monthly. Same capture quality on both;
    | only the grant, retention, and token lifetime differ.
    |
    */

    'plans' => [
        'free' => [
            'name' => 'Try',
            'credits' => (int) env('REVISEMY_FREE_CREDITS', 20),
            'renews' => false,
            'review_retention_days' => (int) env('REVISEMY_FREE_RETENTION_DAYS', 7),
            'token_days' => (int) env('REVISEMY_FREE_TOKEN_DAYS', 14),
        ],
        'pro' => [
            'name' => 'Plus',
            'credits' => (int) env('REVISEMY_PRO_CREDITS', 100),
            'renews' => true,
            'review_retention_days' => (int) env('REVISEMY_PRO_RETENTION_DAYS', 90),
            'token_days' => (int) env('REVISEMY_PRO_TOKEN_DAYS', 365),
            'price_usd' => 9,
            'paddle_price' => env('PADDLE_PRICE_PRO'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Try-token mint limits (shared API + homepage Livewire)
    |--------------------------------------------------------------------------
    |
    | Caps new workspaces per client IP so Try packs cannot be farmed forever.
    |
    */

    'try_token' => [
        'per_hour' => (int) env('REVISEMY_TRY_TOKEN_PER_HOUR', 3),
        'per_day' => (int) env('REVISEMY_TRY_TOKEN_PER_DAY', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit burn table (create_review sources)
    |--------------------------------------------------------------------------
    */

    'costs' => [
        'images' => 1,
        'pdf' => 1,
        'html' => 3,
        'capture_url' => 5,
    ],

];
