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

    'version' => '1.12.0',

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
    | wait_for_review holds one call open until the person acts on the review,
    | checking every wait_poll_ms, and answers changed:false after
    | wait_max_seconds so the agent can call again. Keep the hold under the
    | host's tool timeout (Claude: 240s) and any proxy's request timeout. Set
    | it to 0 to answer at once, like get_review.
    */
    'wait_max_seconds' => (int) env('REVISEMY_WAIT_MAX_SECONDS', 45),
    'wait_poll_ms' => (int) env('REVISEMY_WAIT_POLL_MS', 2000),
    'wait_heartbeat_ms' => (int) env('REVISEMY_WAIT_HEARTBEAT_MS', 10000),

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
        // Longest Browserless session your plan allows (ms; 2 minutes on
        // most plans). Every /function wait is fitted inside it, the scroll
        // sweep giving way first; a ?timeout= over the plan's cap gets the
        // request turned down and the capture falls back to /screenshot.
        // 0 sends whatever the waits add up to.
        'session_max_ms' => (int) env('REVISEMY_CAPTURE_SESSION_MAX_MS', 120_000) ?: null,
        // How many viewports to shoot side by side, each in its own browser
        // session. Match it to your plan's concurrent browsers; 1 shoots
        // them one after another. A viewport turned away for being over the
        // limit (HTTP 429) is asked again on its own.
        // Bars fixed to the bottom of the screen (tab bars, "Book now" bars)
        // are drawn once at the end of a full-page shot instead of over the
        // first screen, and sticky bottom bars go back to their place in the
        // page. false shoots them where the first screen has them.
        'place_fixed' => filter_var(env('REVISEMY_CAPTURE_PLACE_FIXED', true), FILTER_VALIDATE_BOOL),
        'concurrency' => max(1, (int) env('REVISEMY_CAPTURE_CONCURRENCY', 3)),
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
        // Load gate after the sweep: every image, CSS background, playing
        // video's first frame and font, then a quiet network (giving up after
        // load_timeout_ms). /function also waits for network idle this long.
        'load_timeout_ms' => max(1_000, (int) env('REVISEMY_CAPTURE_LOAD_TIMEOUT_MS', 8_000)),
        // Hide cookie consent banners (OneTrust, Cookiebot, …) on URL captures.
        'hide_consent' => filter_var(env('REVISEMY_CAPTURE_HIDE_CONSENT', true), FILTER_VALIDATE_BOOL),
        // Hide chat launchers (Intercom, Drift, HubSpot, Crisp, Zendesk, …),
        // which a full-page shot leaves floating mid-page.
        'hide_chat' => filter_var(env('REVISEMY_CAPTURE_HIDE_CHAT', true), FILTER_VALIDATE_BOOL),
        // Record selector, kind, text and box for visible elements per shot.
        'collect_elements' => filter_var(env('REVISEMY_CAPTURE_COLLECT_ELEMENTS', true), FILTER_VALIDATE_BOOL),
        // Desktop viewports send this user agent instead of HeadlessChrome's,
        // which bot walls turn away. Empty keeps the browser's own.
        'desktop_user_agent' => env('REVISEMY_CAPTURE_DESKTOP_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
        // Mobile viewports render as a phone: isMobile, touch, this user agent.
        'mobile_user_agent' => env('REVISEMY_CAPTURE_MOBILE_USER_AGENT', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1'),
        // Viewports with 'device' => 'tablet' render as an iPad instead.
        'tablet_user_agent' => env('REVISEMY_CAPTURE_TABLET_USER_AGENT', 'Mozilla/5.0 (iPad; CPU OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1'),
        'chrome_path' => env('REVISEMY_CAPTURE_CHROME_PATH', PHP_OS_FAMILY === 'Darwin'
            ? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
            : null),
        'node_modules' => env('REVISEMY_CAPTURE_NODE_MODULES', base_path('node_modules')),
        // [width, height] plus optional 'dpr' (overrides url_device_scale_factor),
        // 'mobile' (isMobile + touch + mobile user agent) and 'device' => 'tablet'
        // (the tablet user agent). Order is the screenshot order agents see;
        // desktop first.
        'viewports' => [
            'desktop' => [1280, 800, 'dpr' => 2],
            'mobile' => [375, 812, 'dpr' => 2, 'mobile' => true],
            'tablet' => [768, 1024, 'dpr' => 2, 'mobile' => true, 'device' => 'tablet'],
        ],
        // Retina captures: 2× device pixels (Browserless deviceScaleFactor / Browsershot DPR).
        // Used for HTML/email (short pages). URL captures use each viewport's 'dpr'.
        'device_scale_factor' => max(1, (int) env('REVISEMY_CAPTURE_DPR', 2)),
        // Full scroll height for website URL capture.
        'url_full_page' => filter_var(env('REVISEMY_CAPTURE_URL_FULL_PAGE', true), FILTER_VALIDATE_BOOL),
        // DPR for viewports without their own 'dpr', and for wide viewports on
        // paths that can't measure the page first (/screenshot, Browsershot):
        // a tall 2× PNG there can run Cloud's 256MB PHP out of memory.
        'url_device_scale_factor' => max(1, (int) env('REVISEMY_CAPTURE_URL_DPR', 1)),
        // /function shoots at the viewport's DPR, scaled down only as far as
        // it takes to stay under this many pixels and Chrome's 16,384px
        // screenshot height. 41M is a 2× desktop shot 8,000 CSS px tall; past
        // that a long page loses a little sharpness instead of memory or
        // content. Big shots are WebP, so they stay well under the byte cap.
        'max_pixels' => max(1_000_000, (int) env('REVISEMY_CAPTURE_MAX_PIXELS', 41_000_000)),
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

    /*
    |--------------------------------------------------------------------------
    | Connector sign-in
    |--------------------------------------------------------------------------
    |
    | A browser that connected before skips the consent screen only when the
    | code goes back to one of these https hosts (or a subdomain of one), a
    | desktop app's own scheme (mcp.custom_schemes), or a loopback address on
    | any port. Every other return address is asked. See
    | App\Support\AssistantCallback.
    |
    */

    'oauth' => [
        // A try token for revisemy:probe-connect, so the hourly check connects
        // to one workspace instead of making a new one each time. Unset, the
        // hourly check doesn't run.
        'probe_token' => env('REVISEMY_PROBE_TOKEN'),

        'trusted_redirect_hosts' => [
            'claude.ai',
            'claude.com',
            'chatgpt.com',
            'grok.com',
            'x.ai',
            'vscode.dev',
        ],
    ],

];
