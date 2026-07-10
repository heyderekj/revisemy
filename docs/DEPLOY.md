# Deploy to Laravel Cloud (contest)

Repo: https://github.com/heyderekj/revisemy

## Steps

1. Open https://cloud.laravel.com and sign in.
2. **New application** → import `heyderekj/revisemy`.
3. Attach **Postgres** and **object storage**.
4. Environment variables:
   - `APP_NAME=ReviseMy`
   - `APP_URL=https://YOUR-APP.laravel.cloud` (set after first deploy if needed)
   - `REVISEMY_DISK` / `FILESYSTEM_DISK` → Cloud object storage disk name
5. Build commands should include `npm ci && npm run build` (Cloud default for Node apps) and `composer install`.
6. After deploy, run: `php artisan migrate --force` and `php artisan storage:link` (if using local public disk; object storage usually needs no link).
7. Visit the `*.laravel.cloud` homepage → **Get a try token** → paste MCP config into any project.
8. Contest reply: post that `https://….laravel.cloud` URL.

## Local verify before Cloud

```bash
php artisan serve
# Get try token, create review via API/MCP, open /r/{token}
```
