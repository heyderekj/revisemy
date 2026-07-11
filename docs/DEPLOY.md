# Deploy to Laravel Cloud (contest)

Repo: https://github.com/heyderekj/revisemy

## Steps

1. Open https://cloud.laravel.com and sign in.
2. **New application** → import `heyderekj/revisemy`.
3. Attach **Postgres**, **object storage**, and a **queue worker**.
   - Do **not** use SQLite on Cloud. The app filesystem is ephemeral, so `database/database.sqlite` disappears on every deploy and try-token / reviews will 500.
4. Environment variables:
   - `APP_NAME=ReviseMy`
   - `APP_URL=https://YOUR-APP.laravel.cloud` (set after first deploy if needed)
   - `DB_CONNECTION=pgsql` (or let Cloud inject `DATABASE_URL` from the attached Postgres resource)
   - `CACHE_STORE` / `SESSION_DRIVER` → `database` or Cloud Redis (not file/sqlite-backed paths)
   - `REVISEMY_DISK` / `FILESYSTEM_DISK` → Cloud object storage disk name
   - `QUEUE_CONNECTION` → Cloud queue (or `database` with a worker)
   - `REVISEMY_SECOND_OPINION=true` (default)
   - Optional: `OPENAI_API_KEY` — upgrades second opinion with vision
5. Build commands should include `npm ci && npm run build` (Cloud default for Node apps) and `composer install`.
6. After deploy, run: `php artisan migrate --force` and `php artisan storage:link` (if using local public disk; object storage usually needs no link).
7. Visit the `*.laravel.cloud` homepage → **Get a try token** → paste MCP config into any project.
8. Contest reply: post that `https://….laravel.cloud` URL.

## Why the queue worker matters

Every screenshot upload dispatches `GenerateSecondOpinionJob` (free design checklist; OpenAI when keyed). Without a worker, reviews still work for human marks — second opinion stays `queued`.

## Local verify before Cloud

```bash
composer run dev
# or: php artisan serve + php artisan queue:listen + npm run dev
# Get try token, create review via API/MCP, open /r/{token}
```
