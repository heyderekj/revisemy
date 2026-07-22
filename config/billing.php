<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plans & monthly credit grants
    |--------------------------------------------------------------------------
    |
    | Free and Pro get the same capture quality. Only the monthly credit
    | allowance (and retention / token lifetime) differs.
    |
    */

    'plans' => [
        'free' => [
            'name' => 'Free',
            'credits' => (int) env('REVISEMY_FREE_CREDITS', 30),
            'review_retention_days' => (int) env('REVISEMY_FREE_RETENTION_DAYS', 7),
            'token_days' => (int) env('REVISEMY_FREE_TOKEN_DAYS', 30),
        ],
        'pro' => [
            'name' => 'Pro',
            'credits' => (int) env('REVISEMY_PRO_CREDITS', 100),
            'review_retention_days' => (int) env('REVISEMY_PRO_RETENTION_DAYS', 90),
            'token_days' => (int) env('REVISEMY_PRO_TOKEN_DAYS', 365),
            'price_usd' => 9,
            'paddle_price' => env('PADDLE_PRICE_PRO'),
        ],
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
