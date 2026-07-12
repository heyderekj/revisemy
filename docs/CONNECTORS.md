# Connectors & packaging (post-v1)

ReviseMy’s product surface is **MCP tools** (`create_review`, `get_review`, `list_reviews`, `add_screenshot`) plus a thin REST API. Plugins and marketplace listings are install UX on top of the same Cloud-hosted (or self-hosted) endpoint.

## Today (v1)

| Host | How |
|------|-----|
| **Cursor** | Paste `mcpServers` JSON into Settings → MCP (homepage tab) |
| **Claude Code** | `claude mcp add --transport http …` (homepage copies the command) |
| **Claude Desktop** | Same `mcpServers` JSON as Cursor |
| **ChatGPT** | Remote MCP / connector with URL + Bearer, or REST `/api/reviews` |
| **Any MCP client** | HTTP MCP at `/mcp/revisemy` |
| **REST-only agents** | `/api/reviews` with Sanctum Bearer token |

## Inline review (MCP Apps)

`create_review` and `get_review` declare a `ui://revisemy/review-app` resource ([MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview)). Hosts that support the extension (Claude web/desktop, VS Code Copilot, Goose, …) render the review inline in a sandboxed iframe — the same review-page and board surfaces, without leaving the chat:

- **Screenshot view**: marks and second-opinion hints overlaid on the shots; click a spot or drag a box to leave a mark, tap any marker to read its note.
- **Board view**: marks grouped Open → In progress → Resolved → Verified (mirrors `/r/{token}/board`), with verify / reopen inline.
- **Decision bar**: approve / request changes with an optional note.
- A **Refresh** control plus a slow auto-poll while the review is in `changes_requested`, so the board updates as the agent resolves marks (the inline echo of the review page's Echo broadcasting).

These are backed by the app-only `add_mark`, `decide_review`, and `verify_mark` tools. Hosts without MCP Apps (e.g. Claude Code CLI) ignore the UI metadata and use the `review_url` link — the loop is unchanged.

The three app tools are marked `Visibility::App`, so the model does not see them in its tool list. Note this is **hiding, not authorization**: any holder of the Sanctum token can still invoke them by name over the same endpoint. That matches ReviseMy's existing trust model — the token owner *is* the human, exactly as the token-gated `/r/{token}` owner link already assumes. Their descriptions say "human-in-the-loop UI only — agents must never call this," mirroring the "never verify a mark yourself" instruction agents already follow.

The iframe's CSP resource-domain allowlist is derived from `app.url` plus the screenshot disk's URL; override with `REVISEMY_MCP_APP_RESOURCE_DOMAINS` (comma-separated origins) if screenshots load from a CDN/bucket host the derivation can't see.

## Decision webhooks (event-driven pipelines)

Pass `webhook_url` (https) to `create_review` — over MCP or REST — and ReviseMy POSTs to it when the human decides, so CI/CD and other tooling can gate on approval instead of polling `get_review`. Follow-up passes inherit the parent's webhook.

- **Payload**: `{ "event": "review.decided", "decided_at": …, "review": <the get_review agent payload> }` — check `review.status` (`approved` / `changes_requested`) and `review.next_action`.
- **Headers**: `X-ReviseMy-Event`, `X-ReviseMy-Review` (public id), and `X-ReviseMy-Signature: sha256=<hmac>` — an HMAC-SHA256 of the raw body keyed with the review's owner token (the secret in `review_url`, which the creator already holds). Verify it before trusting the payload.
- **Delivery**: queued, 10s timeout, 3 attempts with backoff (10s / 60s / 5m); failures are logged, never block the human's decision.
- **Trust stance**: the token holder chooses the target URL, same as `page_url` capture; the payload contains only data that holder already has. `http://` is allowed only in local/testing environments.

## Next packaging steps

### Cursor plugin / marketplace

- Ship a plugin that stores the user’s try token and points at `https://<app>.laravel.cloud/mcp/revisemy`
- Optional: deep link from the homepage “Add to Cursor” button
- Keep tool names stable so the plugin never forks the protocol

### Claude connector

- Register a remote MCP connector against the web endpoint
- OAuth can come later; Bearer try tokens are enough for the weekend demo

### ChatGPT Action / custom GPT

- Publish an OpenAPI shim that mirrors `/api/reviews` (or MCP-over-HTTP if supported)
- Same auth header pattern

### Agent skill

Add a small `SKILL.md` that teaches agents *when* to call ReviseMy:

- After UI changes, before claiming “done”
- When proposing layout options (A/B/C screenshots)
- When a stakeholder needs a link without installing Cursor

The skill should not reimplement tools — it only points at the MCP server.

For **taste while implementing** (animation easing, press feedback, depth), pair with [emilkowalski/skills](https://github.com/emilkowalski/skills) (`npx skills@latest add emilkowalski/skills`). Those skills guide the coding agent; ReviseMy second opinion stays hints only and never overrides human marks.

## Design rule

Tool names and JSON payloads stay **host-agnostic**. Every connector is: base URL + auth.
