<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Second opinion
    |--------------------------------------------------------------------------
    |
    | After each screenshot upload, a queued job runs a free design checklist.
    | When OPENAI_API_KEY (or a custom OpenAI-compatible base URL such as
    | Ollama) is set, the same job upgrades with a vision pass.
    | Findings are suggestions only — human marks stay authoritative.
    |
    */

    'second_opinion_enabled' => env('REVISEMY_SECOND_OPINION', true),

    /*
    |--------------------------------------------------------------------------
    | Vision provider
    |--------------------------------------------------------------------------
    |
    | Which model critiques screenshots: "anthropic", "openai", or "auto"
    | (prefer Anthropic when its key is set, else OpenAI). With no key and
    | no custom OpenAI base URL the second opinion stays checklist-only.
    |
    */

    'vision' => [
        'provider' => env('REVISEMY_VISION_PROVIDER', 'auto'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('REVISEMY_ANTHROPIC_MODEL', 'claude-opus-4-8'),
        'timeout' => (int) env('REVISEMY_ANTHROPIC_TIMEOUT', 60),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        // null = https://api.openai.com/v1 (Ollama, Groq, OpenRouter, LM Studio, …)
        'base_url' => env('REVISEMY_OPENAI_BASE_URL'),
        'model' => env('REVISEMY_OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('REVISEMY_OPENAI_TIMEOUT', 45),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server-side capture
    |--------------------------------------------------------------------------
    |
    | Lets create_review render page_url or raw email HTML into screenshots.
    | Driver "hosted" posts to a Browserless-compatible screenshot API (no
    | Chrome needed in the app container — required on Laravel Cloud);
    | "browsershot" uses a local Chrome via spatie/browsershot. Null = off.
    |
    */

    'capture' => [
        'driver' => env('REVISEMY_CAPTURE_DRIVER'),
        'endpoint' => env('REVISEMY_CAPTURE_ENDPOINT'),
        'api_key' => env('REVISEMY_CAPTURE_KEY'),
        'timeout' => (int) env('REVISEMY_CAPTURE_TIMEOUT', 30),
        'viewports' => [
            'mobile' => [375, 812],
            'desktop' => [1280, 800],
        ],
    ],

];
