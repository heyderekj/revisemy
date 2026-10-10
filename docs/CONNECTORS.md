# Connectors & packaging (post-v1)

ReviseMy’s product surface is **MCP tools** (`create_review`, `get_review`, `list_reviews`, `add_screenshot`) plus a thin REST API. Plugins and marketplace listings are install UX on top of the same Cloud-hosted (or self-hosted) endpoint.

## Today (v1)

| Host | How |
|------|-----|
| **Claude (web, desktop, phone)** | Customize → Connectors → Add custom connector with the URL → **Connect** (OAuth, makes a try workspace) — inline review via MCP Apps |
| **ChatGPT** | Settings → Connectors → custom connector with the URL → **Connect** (OAuth only; the app takes no key) |
| **Cursor / VS Code** | One-click deep links (`App\Support\InstallLinks`), then sign in — VS Code renders the review inline |
| **Claude Code** | `claude mcp add --transport http revisemy <url>`, then `/mcp` to sign in — agent shares `review_url` |
| **Grok** | grok.com/connectors → New Connector → Custom, paste the URL, click Connect (OAuth; the form has no token field). Grok does not render MCP Apps, so the agent must paste `review_url` in the chat. The CLI can still send a try token as a Bearer header |
| **Muse** | Get a try token, then paste the full prompt on the connect page. It includes the address, bearer token, and the checkup loop. Signing in inside Muse is still rough |
| **Codex** | `[mcp_servers.revisemy]` in `~/.codex/config.toml` with `bearer_token_env_var` |
| **Any MCP client** | HTTP MCP at `/mcp/revisemy`: OAuth, or a Bearer try token |
| **REST-only agents** | `/api/reviews` with Sanctum Bearer token |

## When assistants use ReviseMy

MCP has no keyword triggers: the model picks a tool from its description and the server's instructions. `App\Support\AssistantPhrases::WHEN` opens the instructions with when ReviseMy fits (something visual to judge before it ships) and when it doesn't (code or PR review), and `create_review`'s description leads with the same. A workspace adds its own words both ways on **Your reviews** (`⚡review-phrases`), stored in `workspaces.assistant_phrases` and added, quoted, to the instructions for that workspace's sessions. Hosts read instructions when a chat starts, so a change applies to the next chat. The `check_page` prompt (url, optional focus) starts a review of one live page from a host's prompt menu; `design_checkup_loop` covers the whole loop.

## Inline review (MCP Apps)

`create_review` and `get_review` declare a `ui://revisemy/review-app` resource ([MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview)). Hosts that support the extension (Claude web/desktop, Copilot, Goose, …) render the review inline in a sandboxed iframe — the human loop (mark, verify, decide) plus a board view, without leaving the chat. The full owner workspace (comment threads, share/guest, drag columns, second-opinion triage) remains on `review_url` / `board_url`.

- **Screenshot view**: marks and second-opinion hints overlaid on the shots; click a spot or drag a box to leave a mark; open a mark for the focus-cropped detail (same `MarkFocus` crop as the web board sheet).
- **Board view**: marks grouped Open → In progress → Resolved → Verified (including previous-pass marks), with a detail panel, verify / reopen, and a link out when comments exist.
- **Decision bar**: approve / request changes with an optional note (`h-8` controls aligned with web Flux `size="sm"`).
- **While the review is made**: the host sends the call's arguments (`ui/notifications/tool-input`) before the result, so the board says what it's capturing (page host, which viewports) with an elapsed timer instead of a blank loading line. A failed call shows the tool's error, and a cancelled one says so. Hosts that send a `progressToken` also get `notifications/progress` from `create_review` for each step: opening the page, each viewport, saving (`App\Support\ToolProgress`). Without a token the call answers once, in JSON, as before.
- A **Refresh** control plus a slow auto-poll while the review is `pending` or `changes_requested`, so the board updates as the agent resolves marks.

These are backed by the app-only `add_mark`, `decide_review`, and `verify_mark` tools. Hosts without MCP Apps (Grok, Claude Code CLI, ChatGPT) ignore the UI metadata. `create_review` and `get_review` still return `review_url` as plain text, and the agent must paste that link. The loop is unchanged.

### MCP ↔ web parity checklist

When changing review/board chrome, ship the matching update in `resources/views/mcp/review-app.blade.php` in the **same PR**.

| Keep aligned | Source of truth |
|--------------|-----------------|
| Marker / status / severity colors & labels | `Annotation::markerClass()`, `statusBadgeClass()`, `severityLabels()`, `statusLabels()` |
| Board column owners & empty copy | `Annotation::boardColumnMeta()` |
| Mark focus crop | `MarkFocus` → pin `focus_preview` in `Review::markToArray()` |
| Control height | Web Flux `size="sm"` (`h-8`) |

**Intentionally web-only:** comment threads (inline shows `comment_count` + “View comments” → `board_url` / `review_url`), share/guest link management, the “Your reviews” link in the header, drag-and-drop column moves, second-opinion accept/dismiss/refresh, title edit, Echo realtime.

The three app tools are marked `Visibility::App`, so the model does not see them in its tool list. Note this is **hiding, not authorization**: any holder of the Sanctum token can still invoke them by name over the same endpoint. That matches ReviseMy's existing trust model — the token owner *is* the human, exactly as the token-gated `/r/{token}` owner link already assumes. Their descriptions say "human-in-the-loop UI only — agents must never call this," mirroring the "never verify a mark yourself" instruction agents already follow.

The iframe's CSP resource-domain allowlist is derived from `app.url` plus the screenshot disk's URL; override with `REVISEMY_MCP_APP_RESOURCE_DOMAINS` (comma-separated origins) if screenshots load from a CDN/bucket host the derivation can't see.

## Decision webhooks (event-driven pipelines)

Pass `webhook_url` (https) to `create_review` — over MCP or REST — and ReviseMy POSTs to it when the human decides, so CI/CD and other tooling can gate on approval instead of polling `get_review`. Follow-up passes inherit the parent's webhook.

- **Payload**: `{ "event": "review.decided", "decided_at": …, "review": <the get_review agent payload> }` — check `review.status` (`approved` / `changes_requested`) and `review.next_action`.
- **Headers**: `X-ReviseMy-Event`, `X-ReviseMy-Review` (public id), and `X-ReviseMy-Signature: sha256=<hmac>` — an HMAC-SHA256 of the raw body keyed with the review's owner token (the secret in `review_url`, which the creator already holds). Verify it before trusting the payload.
- **Delivery**: queued, 10s timeout, 3 attempts with backoff (10s / 60s / 5m); redirects aren't followed and count as a failure. Failures are logged and never block the human's decision. After 5 deliveries in a row fail, the webhook pauses; `get_review` shows `webhook: { paused, failures, last_error }` (never the URL). A later pass inherits the pause; passing `webhook_url` again starts fresh.
- **Trust stance**: the token holder chooses the target URL and the payload contains only data that holder already has, but the request leaves from this container, so the URL must not resolve to a private, loopback or link-local address — checked when it's saved and again before every send (`App\Support\OutboundUrl`). `http://` is allowed only in local/testing environments.

## Next packaging steps

### Cursor plugin / marketplace

- Ship a plugin that stores the user’s try token and points at `https://<app>.laravel.cloud/mcp/revisemy`
- Optional: deep link from the homepage “Add to Cursor” button
- Keep tool names stable so the plugin never forks the protocol

### Connect (OAuth)

Shipped. `routes/ai.php` accepts a Sanctum try token or a Passport access token (`AuthenticateMcp`) and serves the OAuth discovery documents and dynamic registration (`Mcp::oauthRoutes()`). An assistant that signs in is sent to `/connect`, which is one button: it makes a try workspace (the same rate limit as Get a try token) or, with a pasted try token, attaches to that workspace. That click is the consent, so Passport's own page is skipped once (`App\Models\OAuthClient::skipsAuthorization`). After that, a browser that is still signed in skips consent only when the code goes back to an assistant we know (`App\Support\AssistantCallback`: Claude, ChatGPT, Grok, VS Code, Cursor, or a loopback port). Registration is open, so any other return address always asks on `resources/views/oauth/authorize.blade.php`. Cursor and VS Code register `cursor://` and `vscode://` callbacks, allowed by `config/mcp.php` (`custom_schemes`). Claude Code's `http://localhost:<port>` callback may come back on any port. Tokens last an hour and refresh for 60 days, with a 60-second grace on a just-swapped refresh token. Only `PASSPORT_PRIVATE_KEY` is needed: the public key is worked out from it (`App\Support\PassportKeys`), because a mismatched pair turned down every token Claude was given on 2026-10-10 (`docs/CONNECTOR-FAILURES.md`). `routes/oauth.php` re-registers authorize, token and registration with a `connect.*` log line each and per-assistant rate limits. Discovery documents and the 401 challenge come from `App\Support\OAuthMetadata`. The Connect and consent pages name the assistant by its return address (`AssistantCallback::identify()`), not the name it registered, and warn when a client named like Claude, ChatGPT or Grok returns somewhere else. A bad sign-in link renders `resources/views/oauth/error.blade.php` instead of Passport's JSON (`RenderAuthorizeError`). After a deploy, `php artisan revisemy:probe-connect` runs Claude's whole flow and names the failing step. Tests: `tests/Feature/McpOAuthTest.php`, `ConnectReliabilityTest.php`, `ProbeConnectTest.php`. A missing Passport key pair must not turn the URL probe into a 500 — Grok, Claude and ChatGPT only start Connect after a 401 with `WWW-Authenticate`.

### ChatGPT Action / custom GPT

- Publish an OpenAPI shim that mirrors `/api/reviews` (or MCP-over-HTTP if supported)
- Same auth header pattern

### Agent skill

Add a small `SKILL.md` that teaches agents *when* to call ReviseMy:

- After UI changes, before claiming “done”
- When proposing layout options (A/B/C screenshots)
- When a stakeholder needs a link without installing Cursor

The skill should not reimplement tools — it only points at the MCP server.

For **taste while implementing** (animation easing, press feedback, depth), pair with [emilkowalski/skills](https://github.com/emilkowalski/skills) (`npx skills@latest add emilkowalski/skills`). Those skills guide the coding agent; ReviseMy second opinion stays hints only, discloses craft lenses via the review chip / `taste` payload, and never overrides human marks. After human marks are fixed (or on a polish pass), run `find-animation-opportunities` against the codebase — not as a second-opinion lens (captures are stills).

## Design rule

Tool names and JSON payloads stay **host-agnostic**. Every connector is: base URL + auth. The one list of hosts and their steps lives in `config/hosts.php` (`connect`), rendered by the connect hub on `/connect`, the homepage and `/connectors`.
