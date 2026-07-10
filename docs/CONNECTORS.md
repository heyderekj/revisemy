# Connectors & packaging (post-v1)

ReviseMy’s product surface is **MCP tools** (`create_review`, `get_review`, `list_reviews`, `add_screenshot`) plus a thin REST API. Plugins and marketplace listings are install UX on top of the same Cloud-hosted (or self-hosted) endpoint.

## Today (v1)

| Host | How |
|------|-----|
| **Cursor** | MCP server config with `url` + `Authorization: Bearer …` header (homepage copies this for you) |
| **Claude Code / Desktop** | Remote MCP / custom connector with the same URL + Bearer token |
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

## Design rule

Tool names and JSON payloads stay **host-agnostic**. Every connector is: base URL + auth.
