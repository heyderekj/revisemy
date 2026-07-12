# ReviseMy

**Mark feedback for your agent.**

ReviseMy is an open-source human-in-the-loop design review tool. Your agent captures **UI, websites, slides, or email** from screenshots, a URL, PDF, or HTML over [Laravel MCP](https://laravel.com/docs/mcp), you open a review link, **mark** what matters like a design critique, then approve or request changes. The agent reads structured work packets and keeps going.

Built with Laravel, Livewire, [Flux](https://fluxui.dev/), Sanctum, and Laravel MCP — ready for [Laravel Cloud](https://cloud.laravel.com).

## Features

- **Marks, not pins** — product UI speaks in marks (rose rectangles + M1/M2 badges). Human marks are authoritative; API keys stay `pins` for compatibility.
- **Rectangle-first review** — drag to outline a region or click for a point note; zoom with +/− and pan with Space+drag (or middle mouse).
- **Second opinion (hints only)** — Cloud-queued, type-aware design checklist on every screenshot; optional Claude (Anthropic) or OpenAI-compatible vision when keyed (or pointed at local Ollama). Sky S-markers never override your marks — accept or dismiss them in the review UI.
- **Review types** — `ui`, `website`, `presentation` (Slide in the UI), or `email`: each gets its own checklist and vision lens (emails get CTA/dark-mode/client checks, slides get slide-density checks, sites get above-the-fold/responsive checks).
- **Four ways to ingest** — `images` (https URL, data URL, or base64), `capture_url` + `page_url` (mobile + desktop screenshots), `pdf` (one shot per page), or raw `html` (email at ~600px). URL capture also stores a DOM snapshot for grounding.
- **Before/after evidence** — agents attach an `after_image` when resolving a mark; the review page and board show a before/after crop next to the resolution note.
- **Mark lifecycle + board** — `/r/{token}/board` groups marks Open → In progress → Resolved → Verified. Agents call `resolve_marks` as they fix code; only the human verifies or reopens.
- **Thread comments** — reply on a mark without moving it on the canvas.
- **Agent subagent path** — `add_findings` drops suggestion / a11y / polish notes into the same review before you look.
- **Work packets + `next_action`** — agents know whether to wait, apply marks, open the next pass, or stop.
- **Multi-pass checkups** — `create_review` with `parent_id` for pass 2+ after you request changes (type and webhook inherit from the parent).
- **Decision webhooks** — optional `webhook_url` on `create_review`; ReviseMy POSTs a signed `review.decided` event when the human approves or requests changes, so CI/CD can gate without polling.
- **Guest share links** — `guest_share_url` lets stakeholders leave suggestions (not authoritative marks); the owner accepts or dismisses them.
- **MCP Apps inline review** — in hosts that support [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview) (Claude web/desktop, Copilot, …), `create_review` / `get_review` render the review inline so humans can mark and decide without leaving chat. Cursor and Claude Code use the `review_url` link instead.
- **Try token, no account** — one-click token on the homepage; paste MCP config for ChatGPT, Claude, Copilot, Cursor, or Grok.
- **Secret review links** — humans open `/r/{token}` without signing up.
- **Live updates (optional)** — Laravel Reverb for realtime board/mark updates; without it the UI polls gracefully.

## Try it on any project (~2 minutes)

1. Open the hosted app (your `*.laravel.cloud` URL after deploy).
2. Click **Get a try token**.
3. Copy the MCP config for your client (ChatGPT, Claude, Copilot, Cursor, or Grok).
4. Ask your agent to capture the work (screenshot, URL, PDF slides, or email HTML) and call `create_review`.
5. Open the review link (or use inline MCP Apps if your host supports it), mark feedback, approve or request changes.
6. Ask the agent to call `get_review` and follow `next_action` — or listen on your `webhook_url` if you set one.

No account required for the human reviewer.

## MCP tools

| Tool | Purpose |
|------|---------|
| `create_review` | title + one source — `images`, `capture_url` (renders `page_url`), `pdf`, or `html` — (+ optional `type`, `page_url`, `parent_id`, `webhook_url`) → review URL; queues second opinion |
| `get_review` | work packets + `next_action` (`wait_for_human` / `apply_pins_then_next_pass` / `done`) |
| `list_reviews` | recent reviews for this try token |
| `add_screenshot` | append a shot to an open review |
| `add_findings` | agent subagent — push suggestion/a11y/polish into the review |
| `resolve_marks` | agent progress on human marks: `in_progress` → `resolved` (+ note, optional `after_image`); never `verified` |
| `request_second_opinion` | re-queue Cloud checklist (+ vision if keyed) |
| `add_mark` | **MCP Apps UI only** — human leaves a mark inline (agents must not call) |
| `decide_review` | **MCP Apps UI only** — human approves or requests changes |
| `verify_mark` | **MCP Apps UI only** — human verifies or reopens a resolved mark |

Prompt: `design_checkup_loop` — full agent↔human checkup cycle.

Screenshots accept **https URLs**, **data URLs**, or **base64**.

**Terminology:** UI copy uses *marks*; JSON still uses `work_packets.pins`, `related_pin`, and `apply_pins_then_next_pass`. Second opinion is suggestions only — see [docs/SECOND-OPINION.md](docs/SECOND-OPINION.md). Webhooks and MCP Apps details — see [docs/CONNECTORS.md](docs/CONNECTORS.md).

### MCP config (Cursor example)

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

The homepage also copies configs for Claude Desktop, Copilot, Claude Code, and ChatGPT/Grok hints. Same URL + Bearer header works for any MCP host — see [docs/CONNECTORS.md](docs/CONNECTORS.md).

### REST API (same auth)

- `POST /api/try-token` — create a try workspace + token
- `POST /api/reviews` — `{ "title", "context?", "type?", "page_url?", "parent_id?", "webhook_url?", "images"|"capture_url"|"pdf"|"html" }`
- `GET /api/reviews/{id}`
- `GET /api/reviews`
- `POST /api/reviews/{id}/screenshots` — `{ "image" }`
- `POST /api/reviews/{id}/findings` — `{ "findings": [...] }`
- `POST /api/reviews/{id}/marks/resolve` — same payload as `resolve_marks`
- `POST /api/reviews/{id}/second-opinion` — optional `{ "screenshot_index" }`

## Local development

```bash
composer setup          # install, .env, key, migrate, npm build
composer dev            # server + queue + logs + vite (all-in-one)
```

Or step by step:

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

Run a queue worker (`php artisan queue:listen`) when testing second opinion or webhooks locally.

Visit `http://127.0.0.1:8000`, get a try token, and create a review.

### Tests

```bash
composer test           # 122 tests
```

Key suites for contributors:

| File | What it covers |
|------|----------------|
| `tests/Feature/McpCreateReviewTest.php` | `create_review` / `get_review` over MCP for every input type |
| `tests/Feature/McpAppTest.php` | MCP Apps UI tools (`add_mark`, `decide_review`, `verify_mark`) |
| `tests/Feature/CaptureIngestionTest.php` | same ingestion paths via REST |
| `tests/Feature/ReviseMyFlowTest.php` | try token, guest share, findings, multi-pass loop |

MCP tests use `ReviseMyServer::actingAs($user)->tool(...)` — no Cursor config required.

## Deploy on Laravel Cloud

1. Push this repo to GitHub.
2. In [Laravel Cloud](https://cloud.laravel.com): **New application** → import the repo.
3. Attach **Postgres** and **object storage** (do not use SQLite on Cloud — it does not persist across deploys).
4. Set env:
   - `APP_NAME=ReviseMy`
   - `FILESYSTEM_DISK` / `REVISEMY_DISK` to the Cloud object storage disk
   - `APP_URL` to your `https://….laravel.cloud` URL
   - Queue worker enabled (`QUEUE_CONNECTION`)
   - Optional `ANTHROPIC_API_KEY` (or `OPENAI_API_KEY`) for the vision second opinion — `REVISEMY_VISION_PROVIDER=auto` prefers Claude when both are set
   - Optional free local vision: run Ollama, set `REVISEMY_VISION_PROVIDER=openai`, `REVISEMY_OPENAI_BASE_URL=http://localhost:11434/v1`, and `REVISEMY_OPENAI_MODEL=llama3.2-vision` (blank key is fine). Local/OSS models give helpful hints, not Claude/GPT-4o quality.
   - Optional `REVISEMY_CAPTURE_DRIVER=hosted` + `REVISEMY_CAPTURE_ENDPOINT`/`REVISEMY_CAPTURE_KEY` (Browserless-compatible API) for server-side URL/email/PDF capture — Cloud containers have no Chrome, so use the hosted driver there
   - Optional Reverb (`BROADCAST_CONNECTION=reverb` + `REVERB_*`/`VITE_REVERB_*`) for live updates — without it the UI polls
5. Deploy. Run migrations from Cloud commands: `php artisan migrate --force`

See [docs/DEPLOY.md](docs/DEPLOY.md) for Postgres pooler tips, Neon wake timeouts, and contest checklist.

## Self-host

Point MCP at your own origin. Use S3-compatible storage for screenshots in production. Rate limits and 7-day review expiry are built in.

For free pixel vision without a cloud API key, point `REVISEMY_OPENAI_BASE_URL` at Ollama (or another OpenAI-compatible `/v1` host) as in `.env.example`.

## Stack

- Laravel 13
- Livewire 4 + Flux UI
- Laravel MCP (HTTP at `/mcp/revisemy`)
- Laravel Sanctum try tokens
- SQLite (local) / Postgres (production) + object storage
- Laravel Reverb (optional realtime)

## Docs

- [CONNECTORS.md](docs/CONNECTORS.md) — ChatGPT / Claude / Cursor / Grok setup, MCP Apps inline review, decision webhooks
- [SECOND-OPINION.md](docs/SECOND-OPINION.md) — second opinion, agent subagent findings, work packets
- [DEPLOY.md](docs/DEPLOY.md) — Laravel Cloud deploy
- `/llms.txt` — agent-oriented site index (on your deployed origin)
- `/sitemap.xml` — public pages for search engines

## License

MIT
