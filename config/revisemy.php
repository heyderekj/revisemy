<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product version (SemVer)
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the public version badge (homepage, MCP app,
    | JSON-LD, changelog). Bump with:
    |   php artisan revisemy:bump {major|minor|patch} [--title=…] [--date=…]
    | then fill highlights in config/changelog.php and commit.
    |
    */

    'version' => '1.4.0',

    /*
    | Koati, the studio's other product. The footer names it as a sibling and
    | links it once it has a public address; there's no connection between the
    | two apps beyond that.
    */
    'koati_url' => env('REVISEMY_KOATI_URL'),

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
        // Empty string must be null — Cloud UI sometimes stores blank vars,
        // and match() would otherwise treat "" as an unknown (disabled) driver.
        'driver' => env('REVISEMY_CAPTURE_DRIVER') ?: null,
        'endpoint' => env('REVISEMY_CAPTURE_ENDPOINT'),
        // Browserless /content-compatible endpoint: POST {url} in, rendered
        // HTML out. Optional — enables DOM snapshots as hidden AI context.
        'content_endpoint' => env('REVISEMY_CAPTURE_CONTENT_ENDPOINT'),
        // Browserless /function endpoint: one page session returns the shot,
        // element map and DOM together. Defaults to the endpoint with
        // /screenshot swapped for /function; hosts without it fall back.
        'function_endpoint' => env('REVISEMY_CAPTURE_FUNCTION_ENDPOINT'),
        'api_key' => env('REVISEMY_CAPTURE_KEY'),
        'timeout' => (int) env('REVISEMY_CAPTURE_TIMEOUT', 30),
        // Post-load pause before the settle script. Short: the settle script
        // itself waits until the page stops moving.
        'wait_ms' => max(0, (int) env('REVISEMY_CAPTURE_WAIT_MS', 1000)),
        // Puppeteer/Browserless navigation waitUntil (networkidle2 recommended).
        'wait_until' => env('REVISEMY_CAPTURE_WAIT_UNTIL', 'networkidle2'),
        // Walk the page before full-page URL shots so scroll-triggered reveals fire.
        // Implemented via waitForFunction (Browserless runs waitForTimeout before
        // scrollPage, so scrollPage alone screenshots mid-animation).
        'scroll_page' => filter_var(env('REVISEMY_CAPTURE_SCROLL_PAGE', true), FILTER_VALIDATE_BOOL),
        'scroll_timeout_ms' => max(5_000, (int) env('REVISEMY_CAPTURE_SCROLL_TIMEOUT_MS', 45_000)),
        'scroll_step_ms' => max(50, (int) env('REVISEMY_CAPTURE_SCROLL_STEP_MS', 175)),
        'scroll_end_settle_ms' => max(200, (int) env('REVISEMY_CAPTURE_SCROLL_END_SETTLE_MS', 450)),
        'scroll_top_settle_ms' => max(100, (int) env('REVISEMY_CAPTURE_SCROLL_TOP_SETTLE_MS', 250)),
        // Settle: fast-forward CSS/WAAPI animations to their end state, hold
        // below-fold reveals open, then wait until the page is still for
        // settle_stable_ms (giving up after settle_timeout_ms).
        'freeze_animations' => filter_var(env('REVISEMY_CAPTURE_FREEZE_ANIMATIONS', true), FILTER_VALIDATE_BOOL),
        'settle_stable_ms' => max(100, (int) env('REVISEMY_CAPTURE_SETTLE_STABLE_MS', 400)),
        'settle_timeout_ms' => max(1_000, (int) env('REVISEMY_CAPTURE_SETTLE_TIMEOUT_MS', 4_000)),
        // Hide cookie consent banners (OneTrust, Cookiebot, …) on URL captures.
        'hide_consent' => filter_var(env('REVISEMY_CAPTURE_HIDE_CONSENT', true), FILTER_VALIDATE_BOOL),
        // Record selector, kind, text and box for visible elements per shot.
        'collect_elements' => filter_var(env('REVISEMY_CAPTURE_COLLECT_ELEMENTS', true), FILTER_VALIDATE_BOOL),
        // Mobile viewports render as a phone: isMobile, touch, this user agent.
        'mobile_user_agent' => env('REVISEMY_CAPTURE_MOBILE_USER_AGENT', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1'),
        'chrome_path' => env('REVISEMY_CAPTURE_CHROME_PATH', PHP_OS_FAMILY === 'Darwin'
            ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
            : null),
        'node_modules' => env('REVISEMY_CAPTURE_NODE_MODULES', base_path('node_modules')),
        // [width, height] plus optional 'dpr' (overrides url_device_scale_factor)
        // and 'mobile' (isMobile + touch + mobile user agent). Order is the
        // screenshot order agents see; desktop first.
        'viewports' => [
            'desktop' => [1280, 800],
            'mobile' => [375, 812, 'dpr' => 2, 'mobile' => true],
            'tablet' => [768, 1024, 'mobile' => true],
        ],
        // Retina captures: 2× device pixels (Browserless deviceScaleFactor / Browsershot DPR).
        // Used for HTML/email (short pages). URL full-page uses url_device_scale_factor.
        'device_scale_factor' => max(1, (int) env('REVISEMY_CAPTURE_DPR', 2)),
        // Full scroll height for website URL capture. DPR 1 avoids Cloud OOM on tall pages.
        'url_full_page' => filter_var(env('REVISEMY_CAPTURE_URL_FULL_PAGE', true), FILTER_VALIDATE_BOOL),
        'url_device_scale_factor' => max(1, (int) env('REVISEMY_CAPTURE_URL_DPR', 1)),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP App (inline review UI)
    |--------------------------------------------------------------------------
    |
    | In hosts that support MCP Apps the review renders inline in a sandboxed
    | iframe. Its CSP resource-domain allowlist is derived from app.url plus
    | the screenshot disk's URL. Set REVISEMY_MCP_APP_RESOURCE_DOMAINS (a
    | comma-separated list of origins) to override when screenshots load from
    | a CDN/bucket host the derivation can't see.
    |
    */

    'mcp_app' => [
        'resource_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('REVISEMY_MCP_APP_RESOURCE_DOMAINS', '')),
        ))),
    ],

];
