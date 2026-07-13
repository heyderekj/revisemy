# Deploy to Laravel Cloud (contest)

Repo: https://github.com/heyderekj/revisemy

## Steps

1. Open https://cloud.laravel.com and sign in.
2. **New application** → import `heyderekj/revisemy`.
3. Attach **Postgres** and **object storage**.
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
   - Optional: `REVISEMY_CAPTURE_DRIVER=hosted` + `REVISEMY_CAPTURE_ENDPOINT`/`REVISEMY_CAPTURE_KEY` (Browserless-compatible API) for URL/email capture on Cloud
   - Optional: `REVISEMY_CAPTURE_DPR=2` (default) — retina captures via Browserless `deviceScaleFactor`
5. Build commands should include `npm ci && npm run build` (Cloud default for Node apps) and `composer install`. Cloud injects database credentials while building Laravel's cached configuration; raw `DB_*` variables may not be available later in the Commands shell.
6. Deploy commands: `php artisan migrate --force` (and `php artisan storage:link` only if using local public disk; object storage usually needs no link).
7. Visit the `*.laravel.cloud` homepage → **Get a try token** → paste MCP config into any project.
8. Contest reply: post that `https://….laravel.cloud` URL.

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
