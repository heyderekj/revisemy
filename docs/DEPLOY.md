# Deploy to Laravel Cloud (contest)

Repo: https://github.com/heyderekj/revisemy

## Steps

1. Open https://cloud.laravel.com and sign in.
2. **New application** → import `heyderekj/revisemy`.
3. Attach a **database** (production runs Laravel MySQL 8.4; Serverless Postgres works too) and **object storage**.
   - Do **not** use SQLite on Cloud. The app filesystem is ephemeral, so `database/database.sqlite` disappears on every deploy and try-token / reviews will 500.
   - A **queue worker** is optional (useful for webhooks); vision second opinion does not need one.
4. Environment variables:
   - `APP_NAME=ReviseMy`
   - `APP_URL=https://YOUR-APP.laravel.cloud` (set after first deploy if needed)
   - `DB_CONNECTION=pgsql`
     - Cloud injects `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, and `DB_DATABASE`. **Custom env vars override injected ones** — delete any manual `DB_USERNAME`, `DB_PASSWORD`, or `DB_URL` you added while debugging.
     - **Do not set `DB_URL` on Cloud** unless you really mean to; a stale URL can force the wrong username (e.g. `laravel`) over the injected credentials.
     - Add **`DB_SSLMODE=require`** (belt-and-suspenders; the app also defaults SSL for Cloud/Neon hosts).
     - **You do not need to edit `DB_HOST` or set `DB_MIGRATE_URL` for pooler hosts.** When `DB_HOST` contains `-pooler`, migrations automatically use the direct host with `sslmode=require` and a longer `connect_timeout`.
     - **Do not put `?options=endpoint%3D...` in `DB_HOST`.** Keep `DB_HOST` as the plain hostname only; the app adds Neon endpoint routing automatically.
     - Optional: set **`DB_MIGRATE_URL`** only if you want an explicit direct URL override.
     - Optional: **`DB_CONNECT_TIMEOUT=60`** (default for serverless hosts) if you need a longer wake window.
   - `CACHE_STORE` / `SESSION_DRIVER` → `database` or Cloud Redis (not file/sqlite-backed paths)
   - `REVISEMY_DISK` / `FILESYSTEM_DISK` → Cloud object storage disk name
   - `QUEUE_CONNECTION` → optional (Cloud queue or `database` if you want workers for webhooks)
   - `REVISEMY_SECOND_OPINION=true` (default)
   - Optional: `ANTHROPIC_API_KEY` or `OPENAI_API_KEY` — vision second opinion with regions on the capture (checklist alone is sidebar text only). No queue worker required.
   - Optional (required for `capture_url`): `REVISEMY_CAPTURE_DRIVER=hosted` (must be the literal string `hosted`, not blank) + `REVISEMY_CAPTURE_ENDPOINT`/`REVISEMY_CAPTURE_KEY` (Browserless-compatible API) for URL/email capture on Cloud
   - Optional: `REVISEMY_CAPTURE_FUNCTION_ENDPOINT` — Browserless `/function`. Defaults to the capture endpoint with `/screenshot` swapped for `/function`; one page session then returns the shot, its rail thumbnail, a vision copy, crop slices, the element map and the DOM, so all of them describe the same frame. This path shoots every viewport at 2×, because it measures the page first. Hosts without it, or a plan that turns it down, fall back to `/screenshot`, which loads the page once through `/content` first to measure it (see `REVISEMY_CAPTURE_URL_DPR`). To see which path a page takes and why, run `php artisan revisemy:probe-capture https://example.com`.
   - Optional: `REVISEMY_CAPTURE_SESSION_MAX_MS=120000` (default) — the longest Browserless session your plan allows (2 minutes on most). Every `/function` wait is fitted inside it: the scroll sweep gives way first (down to 10s), then the load gate, the network-idle wait and the time for shots. A page that never goes quiet still ends with a shot. Over the plan's cap Browserless turns the request down with HTTP 400 and capture falls back to `/screenshot`, so raise it only with your plan. `0` sends whatever the waits add up to (about 124s).
   - Optional: `REVISEMY_CAPTURE_CONCURRENCY=3` (default) — how many viewports are shot side by side, each in its own Browserless session, so a capture takes as long as its slowest viewport instead of all three added up. Match it to your plan's concurrent browsers; `1` shoots them one after another. A viewport turned away for being over the limit (HTTP 429) is asked again on its own. Each shot's meta records `capture_ms` and, on `/function`, `timings` for every phase; every capture also logs one `Capture timings` line.
   - Optional: `REVISEMY_CAPTURE_CONTENT_ENDPOINT` for DOM snapshots when `/function` isn't available; `REVISEMY_CAPTURE_TIMEOUT=60` for heavy marketing pages
   - Optional: `REVISEMY_CAPTURE_WAIT_MS=1000` (default) — short post-load pause; `REVISEMY_CAPTURE_WAIT_UNTIL=networkidle2`
   - Every capture settles before the shot: the scrollbar is hidden so the page lays out at the full viewport width, fonts and images load, CSS/WAAPI animations and transitions jump to their end state, and the page must sit still for `REVISEMY_CAPTURE_SETTLE_STABLE_MS=400` (giving up after `REVISEMY_CAPTURE_SETTLE_TIMEOUT_MS=4000`). `REVISEMY_CAPTURE_FREEZE_ANIMATIONS=false` turns off the fast-forward.
   - Before the stillness wait, a load gate waits for every image, CSS background image, autoplaying video's first frame and font, then for the network to go quiet (giving up after `REVISEMY_CAPTURE_LOAD_TIMEOUT_MS=8000`). `/function` then waits up to 3s more for network idle. Each shot's meta records `loaded` and `incomplete_images`.
   - Optional: `REVISEMY_CAPTURE_SCROLL_PAGE=true` (default) — sweep the page before full-page URL shots so scroll-triggered / IntersectionObserver reveals are visible, and hold them open so they don't play back out (raise `REVISEMY_CAPTURE_SCROLL_TIMEOUT_MS` for very tall pages)
   - Optional: `REVISEMY_CAPTURE_PLACE_FIXED=true` (default) — a full-page shot is the whole page seen from the first screen, so a bar fixed to the bottom of the screen (tab bars, "Book now" bars) would land over the hero. It's drawn once at the end of the page instead, where a visitor last sees it, and sticky bottom bars go back to their place in the page. Top bars already sit where they belong. `false` shoots them where the first screen has them.
   - Optional: `REVISEMY_CAPTURE_HIDE_CONSENT=true` (default) — hide cookie consent banners (OneTrust, Cookiebot, Usercentrics …) on URL captures
   - Optional: `REVISEMY_CAPTURE_HIDE_CHAT=true` (default) — hide chat launchers (Intercom, Drift, HubSpot, Crisp, Zendesk, Tawk, Tidio, LiveChat …), which a full-page shot leaves floating over the middle of the page
   - Optional: `REVISEMY_CAPTURE_COLLECT_ELEMENTS=true` (default) — store each shot's element map next to the image: selector, kind, text, label, role, box, the element it sits in, and its type, colour and spacing. Marks snap to these elements and agents get them with each mark. Maps are cleaned before they're stored (`App\Support\ElementMap`).
   - Viewports (`capture.viewports`): desktop 1280, mobile 375 and tablet 768, each at 2×. Mobile and tablet get touch, `isMobile` and a phone or iPad user agent (`REVISEMY_CAPTURE_MOBILE_USER_AGENT`, `REVISEMY_CAPTURE_TABLET_USER_AGENT`). Desktop reads as Chrome on a Mac (`REVISEMY_CAPTURE_DESKTOP_USER_AGENT`; empty keeps HeadlessChrome's, which bot walls turn away). On `/function` it carries the browser's own Chrome version.
   - Optional: `REVISEMY_CAPTURE_MAX_PIXELS=41000000` (default) — `/function` keeps each shot under this many pixels and under Chrome's 16,000px screenshot height by scaling it down only as far as needed. That holds a 1280 desktop at 2× down to 8,000 CSS px; a longer page is a little softer rather than cut off. Shots up to 4MP stay PNG; bigger ones are WebP (quality 92), which keeps text crisp at a fraction of the bytes, and only one still over 16MB is scaled down further. Meta `dpr` records what the pixels hold per CSS px, and `format` the encoding.
   - Crop slices: when a shot is too big for PHP to decode (over 4MP), `/function` also shoots the whole page as up to 8 JPEG slices at 1×, so `look_at_marks` can crop a mark anywhere on a long page.
   - Optional: `REVISEMY_CAPTURE_DPR=2` (default) — email and HTML captures. `REVISEMY_CAPTURE_URL_DPR=1` (default) — website viewports on paths that can't scale a shot to fit (`/screenshot`, Browsershot), where a tall 2× PNG can run Cloud's 256MB PHP out of memory. On the `/screenshot` fallback a wide viewport still gets 2× when `/content` measured the page short enough (16MP at most: about 3,100 CSS px tall on desktop). Phone-width viewports keep 2× there.
   - PDFs render at 192 DPI (2× a screen), or lower for a page that would pass 4MP, and show at their screen size.
   - Credits: Try = 20/mo rolling by default (`REVISEMY_FREE_CREDITS_RENEW=true`). Paid Plus is off until you set `REVISEMY_PRICING_ENABLED=true`.
   - Hosted billing (Polar, when pricing is on): `POLAR_ACCESS_TOKEN` (organization access token with `checkouts:write`, `customer_sessions:write`, `subscriptions:write`), `POLAR_WEBHOOK_SECRET`, `POLAR_PRODUCT_PLUS` (Plus, $9/mo recurring), `POLAR_PRODUCT_CREDITS_50` (50 credits, $5 one-time). `POLAR_SERVER=sandbox` for testing; defaults to production. Webhook endpoint: `https://revisemy.com/polar/webhook` with events `order.paid`, `order.refunded`, `subscription.created`, `subscription.active`, `subscription.updated`, `subscription.canceled`, `subscription.uncanceled`, `subscription.revoked`. Agents call `create_checkout` (optional `product`) → human opens signed `/billing/checkout/{workspace}` → redirect to Polar. Credits are granted only by the `order.paid` webhook (idempotent per order).
     - Webhook gotchas (from Polar's delivery docs): the URL must be the final one — Polar treats any redirect (apex ↔ `www`, http → https) as a failure; an endpoint is disabled automatically after 10 consecutive non-2xx responses (re-enable it in Polar's webhook settings); behind Cloudflare, turn off Bot Fight Mode or add a WAF skip rule for `/polar/webhook`, since blocked deliveries show up in Polar as 403. Signatures are verified with both of Polar's signing schemes (secrets made before and after 8 Sept 2026), so either secret works.
   - Analytics (Fathom, `FATHOM_SITE_ID`): events fire from the browser, so ad blockers undercount them, and Fathom has no server-side events API. Polar is the ledger for revenue; Fathom's numbers are indicative. Set each purchase event's currency to USD in the Fathom dashboard (values are sent in cents). Event names can't be renamed once created, so these are the stable set:
     - Pricing section: `Pricing viewed` (scrolled into view, once), `Pricing plus cta` (Upgrade via your agent), `Pricing credit costs` (modal opened). The Try card's button is the existing `Try token pricing free`.
     - Billing pages: `Plus purchased` (900) and `Credit pack purchased` (500) on `/billing/success`, once per checkout id per browser tab and only when Polar's return URL carries a valid checkout id and a known product; `Checkout canceled` on `/billing/cancel`; `Checkout unavailable` when a checkout link couldn't open (a broken setup shows here as well as in the log).
     - Pageviews skip `/r/…` (review tokens) and `/billing/manage|checkout/…` (workspace ids).
   - Try mint limits (shared homepage + `POST /api/try-token`): 3/hour and 3/day per client IP (`REVISEMY_TRY_TOKEN_PER_HOUR` / `REVISEMY_TRY_TOKEN_PER_DAY`). Prefer a Cloudflare rate rule on `POST /api/try-token` as defense-in-depth.
   - Connect (OAuth, for Claude, ChatGPT and Grok custom connectors): set `PASSPORT_PRIVATE_KEY` only, as one line. See [Connect on Laravel Cloud](#connect-on-laravel-cloud). Try tokens keep working without it.
   - Support top-up a try workspace: `php artisan revisemy:extend-try {workspace_public_id} --credits=20` (or `--pack` for a full Try pack + token bump).
   - Scale-to-zero: assistants give the sign-in endpoints 10 seconds and a token refresh 30, and every MCP call reads the database. Production keeps the app and database awake (the every-minute scheduler does this today). If you turn the scheduler off, turn scale-to-zero off too, or a cold start can fail Connect.
5. Build commands should include `npm ci && npm run build` (Cloud default for Node apps) and `composer install`. Cloud injects database credentials while building Laravel's cached configuration; raw `DB_*` variables may not be available later in the Commands shell.
6. Run the scheduler (`php artisan schedule:run` every minute — Cloud's scheduler toggle does this). It prunes reviews 30 days past their retention, with their screenshots, and expired tokens, nightly.
7. Optional: `NIGHTWATCH_ENABLED=true` and `NIGHTWATCH_TOKEN` for error tracking (requests are sampled at 10%).
8. Check the install: `cloud command:run "php artisan revisemy:check"` lists each thing as ready or not, with the one thing to do, and exits 1 while anything is missing.
9. Deploy commands: `php artisan migrate --force`, then `php artisan revisemy:check --connect`, which fails the deploy if Connect would be broken (keys that can't check their own tokens, missing OAuth tables). Add `php artisan storage:link` only if using a local public disk.
10. After the deploy, run `php artisan revisemy:probe-connect https://YOUR-APP --token=YOUR_TRY_TOKEN` from any machine. It connects the way Claude does and names the step that fails. Then open `/connect`, connect an assistant, and check the page shows its first call.
11. Contest reply: post that `https://….laravel.cloud` URL.

## Connect on Laravel Cloud

ReviseMy is used through MCP, so Connect is the front door. On 2026-10-10 Claude registered, signed in and got a token, then every MCP call came back 401: the public key on the server didn't check what the private key signed. Nothing logged it. See `docs/CONNECTOR-FAILURES.md`.

**Keys.** Set `PASSPORT_PRIVATE_KEY` only. The public key is worked out from it (`App\Support\PassportKeys`), so the pair can't drift. Cloud's editor takes one `KEY=value` per line, so paste the key as one line with `\n` where the line breaks go:

```bash
php artisan passport:keys --force
awk 'NF {sub(/\r/, ""); printf "%s\\n",$0;}' storage/oauth-private.key
```

Paste the output as `PASSPORT_PRIVATE_KEY="…"`. A leftover `PASSPORT_PUBLIC_KEY` is ignored when it doesn't match, and `revisemy:check` says to remove it. Changing the private key signs everyone out of their assistants; they reconnect from Connect.

**Logs.** Every Connect step writes one `connect.*` line (register, authorize, Connect click, token, refresh, MCP refusal) with the client id, where it returns to, status, OAuth error and timing. Never a code, token or key. On Cloud they go to Cloud's own log channel, so they show under Monitoring → Logs even if `LOG_CHANNEL` is overridden. Don't override Cloud's injected `LOG_CHANNEL` with `stack`/`single`: that sends every other log line to a file inside the container that nobody sees.

**Checks.**
- `php artisan revisemy:check --connect` as a deploy command: a broken key pair fails the deploy, and the last good build keeps serving.
- `php artisan revisemy:probe-connect {url} --token=…` after a deploy, from anywhere. Add `--callback=http://localhost:3118/callback` to run the Claude Code variant.
- Set `REVISEMY_PROBE_TOKEN` to a try token and the scheduler runs the probe hourly, reporting a failure.

**Edge and network.**
- Anthropic's servers call from `160.79.104.0/21`, and ChatGPT's and Grok's from their own clouds. Rate limits on `/oauth/token` are per assistant (`throttle:oauth-token`), not per address, for that reason.
- Leave edge bot categories and "Under attack mode" off: they challenge server-to-server calls.
- With Browser Integrity Check on, the edge answers `403` to the default `Python-urllib` user agent before the app sees it. Most MCP clients send their own user agent and pass.
- `www.revisemy.com` redirects to `revisemy.com`. Hosts drop the bearer token on a cross-host redirect, so the address to paste is always `https://revisemy.com/mcp/revisemy`.
- Claude's limits: 10 seconds for discovery, registration and the token swap; 30 for a refresh. The probe times each step against them.

## “Still waking up” / 30s deploy timeout

Laravel Cloud Serverless Postgres is Neon under the hood. When the database is idle it scales to zero; the next deploy opens a connection that **wakes** the compute. Cloud’s default health/migrate wait is about **30 seconds**. If Neon is still starting, deploy fails with **“still waking up”**.

Cloud’s UI advice maps to two different knobs:

1. **Increase the connection / wake wait** on the Postgres resource (try **60–90s**). That gives deploy/migrate more time for Neon to become ready.
2. **Decrease how often the DB sleeps** by raising the idle/suspend window, or **disable hibernation / scale-to-zero** on the database while you are iterating on deploys (or for contest weekend traffic).

**In Laravel Cloud UI (Serverless Postgres resource):**

- If deploy fails with “still waking up”, bump the **wake / connection wait** above 30s first.
- If every deploy cold-starts the DB, raise the **idle/suspend window** or disable hibernation temporarily.
- Keep the app and database in the **same region**.

After the code-side fixes, runtime stays on the pooled `-pooler` host (good for concurrency) while migrations use the direct host automatically — so you should not need manual `DB_HOST` surgery for pooler vs direct.

## Second opinion (no worker required)

Every screenshot gets a free checklist immediately. When `ANTHROPIC_API_KEY` or `OPENAI_API_KEY` is set, vision enrichment runs **after the HTTP response** in the same process — add the key, refresh, and regions appear on the capture. A queue worker is only needed for other background jobs (e.g. decision webhooks).

## Local verify before Cloud

```bash
composer run dev
# or: php artisan serve + npm run dev
# Get try token, create review via API/MCP, open /r/{token}
```
