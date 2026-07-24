<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paid pricing (Plus / Paddle)
    |--------------------------------------------------------------------------
    |
    | When false, create_checkout is disabled and public/agent copy steers
    | people to the free monthly credit pack. Flip on when Plus is ready.
    |
    */

    'pricing_enabled' => (bool) env('REVISEMY_PRICING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Plans & credit grants
    |--------------------------------------------------------------------------
    |
    | Try (internal key `free`) is the default pack. While pricing is off it
    | renews monthly (same lazy refill as Plus). Plus stays in config so
    | existing subscribers and a future re-enable keep working.
    |
    */

    'plans' => [
        'free' => [
            'name' => 'Try',
            'credits' => (int) env('REVISEMY_FREE_CREDITS', 20),
            'renews' => (bool) env('REVISEMY_FREE_CREDITS_RENEW', true),
            'review_retention_days' => (int) env('REVISEMY_FREE_RETENTION_DAYS', 7),
            'token_days' => (int) env('REVISEMY_FREE_TOKEN_DAYS', 90),
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
