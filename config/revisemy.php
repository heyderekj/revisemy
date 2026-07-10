<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Second opinion
    |--------------------------------------------------------------------------
    |
    | After each screenshot upload, a queued job runs a free design checklist.
    | When OPENAI_API_KEY is set, the same job upgrades with a vision pass.
    | Findings are suggestions only — human marks stay authoritative.
    |
    */

    'second_opinion_enabled' => env('REVISEMY_SECOND_OPINION', true),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('REVISEMY_OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('REVISEMY_OPENAI_TIMEOUT', 45),
    ],

];
