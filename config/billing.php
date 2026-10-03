<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paid pricing (Plus + credit packs, sold through Polar)
    |--------------------------------------------------------------------------
    |
    | When false, create_checkout is disabled and public/agent copy steers
    | people to the free monthly credit pack.
    |
    */

    'pricing_enabled' => (bool) env('REVISEMY_PRICING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Plans & credit grants
    |--------------------------------------------------------------------------
    |
    | Try (internal key `free`) refills lazily each month. Plus refills when
    | Polar bills the subscription (order.paid webhook), so `renews` only
    | describes the plan to agents — the refill itself is webhook-driven.
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
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit packs (one-time Polar purchases)
    |--------------------------------------------------------------------------
    |
    | Any plan can buy one. Purchased credits never expire and are spent only
    | after the monthly grant runs out. The key is the product key below.
    |
    */

    'packs' => [
        'credits_50' => [
            'name' => '50 credits',
            'credits' => 50,
            'price_usd' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Polar
    |--------------------------------------------------------------------------
    |
    | Polar is merchant of record. `products` maps our product keys to Polar
    | product IDs; webhooks arrive at /polar/webhook.
    |
    */

    'polar' => [
        'server' => env('POLAR_SERVER', 'production'), // production | sandbox
        'access_token' => env('POLAR_ACCESS_TOKEN'),
        'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),
        'products' => [
            'plus' => env('POLAR_PRODUCT_PLUS'),
            'credits_50' => env('POLAR_PRODUCT_CREDITS_50'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Try-token mint limits (shared API + homepage Livewire)
    |--------------------------------------------------------------------------
    |
    | Caps new workspaces per client IP so Try packs cannot be farmed forever.
    |
    | Both windows are checked, whichever trips first. Keep per_day above
    | per_hour or the hourly gate is dead code: an equal pair means the daily
    | counter always binds first and the hourly one can never fire.
    |
    | 3/hour shapes the burst; 12/day is the ceiling. A shared office or campus
    | NAT is one IP to us, so a flat 3/day turned every colleague after the
    | third into a support question.
    |
    */

    'try_token' => [
        'per_hour' => (int) env('REVISEMY_TRY_TOKEN_PER_HOUR', 3),
        'per_day' => (int) env('REVISEMY_TRY_TOKEN_PER_DAY', 12),
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
