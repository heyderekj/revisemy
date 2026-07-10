# ReviseMy

**Pin feedback for your agent.**

ReviseMy is an open-source human-in-the-loop design review tool. Your agent uploads UI screenshots over [Laravel MCP](https://laravel.com/docs/mcp), you open a review link, pin notes like a design critique, then approve or request changes. The agent reads structured feedback and keeps going.

Built with Laravel, Livewire, [Flux](https://fluxui.dev/) (the official Livewire UI kit), Sanctum, and Laravel MCP — ready for [Laravel Cloud](https://cloud.laravel.com).

## Try it on any project (~2 minutes)

1. Open the hosted app (your `*.laravel.cloud` URL after deploy).
2. Click **Get a try token**.
3. Paste the Cursor MCP config into any local project.
4. Ask your agent to screenshot UI work and call `create_review`.
5. Open the review link, pin feedback, approve or request changes.
6. Ask the agent to call `get_review` and apply the notes.

No account required for the human reviewer — secret `/r/{token}` links.

## MCP tools

| Tool | Purpose |
|------|---------|
| `create_review` | title + screenshots → review URL |
| `get_review` | status, pins, decision |
| `list_reviews` | recent reviews for this try token |
| `add_screenshot` | append a shot to an open review |

Screenshots accept **https URLs**, **data URLs**, or **base64**.

### Cursor MCP config

After you get a try token from the homepage:

```json
{
  "mcpServers": {
    "revisemy": {
      "url": "https://YOUR-APP.laravel.cloud/mcp/revisemy",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN"
      }
    }
  }
}
```

Same URL + Bearer header works for Claude connectors and other MCP hosts. See [docs/CONNECTORS.md](docs/CONNECTORS.md).

### REST API (same auth)

- `POST /api/try-token` — create a try workspace + token
- `POST /api/reviews` — `{ "title", "context?", "images": [...] }`
- `GET /api/reviews/{id}`
- `GET /api/reviews`
- `POST /api/reviews/{id}/screenshots` — `{ "image" }`

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link
npm install && npm run build
php artisan serve
```

Visit `http://127.0.0.1:8000`, get a try token, and create a review.

## Deploy on Laravel Cloud

1. Push this repo to GitHub.
2. In [Laravel Cloud](https://cloud.laravel.com): **New application** → import the repo.
3. Attach **Postgres** and **object storage**.
4. Set env:
   - `APP_NAME=ReviseMy`
   - `FILESYSTEM_DISK` / `REVISEMY_DISK` to the Cloud object storage disk
   - `APP_URL` to your `https://….laravel.cloud` URL
5. Deploy. Run migrations from Cloud commands: `php artisan migrate --force`
6. Reply to the contest with your `https://….laravel.cloud` homepage URL.

## Self-host

Point MCP at your own origin. Use S3-compatible storage for screenshots in production. Rate limits and 7-day review expiry are built in.

## Stack

- Laravel 13
- Livewire 4 + Flux UI
- Laravel MCP (web endpoint)
- Laravel Sanctum try tokens
- Postgres / SQLite + object storage

## Docs

- [CONNECTORS.md](docs/CONNECTORS.md) — Cursor / Claude / ChatGPT packaging  
- [SECOND-OPINION.md](docs/SECOND-OPINION.md) — design skills + UI element grounding research  
- [DEPLOY.md](docs/DEPLOY.md) — Laravel Cloud contest deploy  

## License

MIT
