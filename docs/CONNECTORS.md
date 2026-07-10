# Connectors & packaging (post-v1)

ReviseMy’s product surface is **MCP tools** (`create_review`, `get_review`, `list_reviews`, `add_screenshot`) plus a thin REST API. Plugins and marketplace listings are install UX on top of the same Cloud-hosted (or self-hosted) endpoint.

## Today (v1)

| Host | How |
|------|-----|
| **Cursor** | Paste `mcpServers` JSON into Settings → MCP (homepage tab) |
| **Claude Code** | `claude mcp add --transport http …` (homepage copies the command) |
| **Claude Desktop** | Same `mcpServers` JSON as Cursor |
| **VS Code** | Paste into `.vscode/mcp.json` using the `servers` key |
| **ChatGPT** | Remote MCP / connector with URL + Bearer, or REST `/api/reviews` |
| **Any MCP client** | HTTP MCP at `/mcp/revisemy` |
| **REST-only agents** | `/api/reviews` with Sanctum Bearer token |

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
