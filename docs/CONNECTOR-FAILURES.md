# Grok connector: failed tries

Written 2026-10-05 after a live probe of `https://revisemy.com/mcp/revisemy` and a read of `heyderekj/revisemy` at `0001c80`. The Grok custom connector still has no tools in chat, even when the connector record says connected.

## What is broken

Grok’s custom connector (grok.com/connectors → New Connector → Custom) pastes one URL and signs in. It has no token field. It only speaks Streaming HTTP or SSE. It discovers tools with `tools/list` after OAuth.

Two different failures got stacked:

1. **Production is not the repo.** `main` has today’s handshake fixes. `revisemy.com` does not. A GET of `/mcp/revisemy` on the live host is still `405` with `content-type: text/html` and an empty body. The route and the exception renderer on `main` both set `application/json`. Changelog on the live site still tops out at 1.5.0 (2026-10-03).
2. **Connect can succeed and still drop the tool list.** That is the state in this chat: ReviseMy is a connected service, and a tool search returns no ReviseMy tools. Grok keeps a connector record after a bad probe. Remove + re-add does not always re-probe. Same class of bug as Claude’s cached connector verdict.

Until (1) is deployed, further commits on `main` do not change what Grok hits.

## Live probe, 2026-10-05 23:04 UTC

| Request | Result |
|---|---|
| `GET /mcp/revisemy` (`Accept: application/json` and `text/event-stream`) | `405`, `content-type: text/html`, `Allow: POST`, empty body |
| `POST /mcp/revisemy` `initialize`, no token | `401` JSON `{"message":"Unauthenticated."}` and `WWW-Authenticate: Bearer realm="mcp", resource_metadata="https://revisemy.com/.well-known/oauth-protected-resource/mcp/revisemy"` |
| `GET /.well-known/oauth-protected-resource` | `200`, resource `https://revisemy.com/mcp/revisemy`, scope `mcp:use` |
| `GET /.well-known/oauth-protected-resource/mcp/revisemy` | `200`, same document |
| `GET /.well-known/oauth-authorization-server` | `200`, authorize / token / register, `S256`, grants `authorization_code` and `refresh_token`, scope `mcp:use` |
| `GET /.well-known/mcp/server-card.json` | `200`, version `1.4.0` |

The 401 challenge is what a host needs to start Connect. The HTML 405 is what a host treats as a broken server if it opens the URL first. Both are true on production at once, depending on the verb.

## Failed tries, in order

All of these landed on `main` on 2026-10-05. None of them are on the host Grok is calling.

| Commit | What it tried | Why it was not enough |
|---|---|---|
| `1ecf853` Make Grok connect by URL, and stop the probe from 500ing | Grok card matches Claude/ChatGPT (URL, no token field). Unauthenticated MCP must be 401 + `WWW-Authenticate`, not a 500 from a Passport guard with no key pair. Origin discovery names the one MCP resource. | Fixed the “never reaches Connect” 500. Live POST is now a correct 401, so this part is on production or was already true. Tools still never loaded. |
| `a23e7b2` Send connector sign-in to the Connect page | Forcing JSON on every `oauth/*` route turned `/oauth/authorize` into a 401, so Grok never saw the Connect button. JSON stays on token and register only. | Browser step. Does not put tools in the connector. |
| `1da90dd` Always return a review link when the host has no MCP app | Grok does not render MCP Apps. `create_review` / `get_review` lead with `review_url`. | Only matters after a tool call. No tools, no review. |
| `aa9939b` Send a failed connector reconnect back to Connect | Remove + add again burned Passport’s one-time approve token and rendered a 403 page. | Reconnect UX. Grok can still keep the old verdict. |
| `6973a81` Let a remembered browser reconnect without the approve token | A browser that already connected finishes authorize instead of 403. Other authorize 403s return to Connect. | Same. Does not clear Grok’s cached tool list. |
| `2bf24a2` Give every connector a complete paste prompt | Grok prompt says to paste `review_url` on its own line. | Copy only. |
| `ca9a634` Register the `mcp:use` scope | Discovery advertised `mcp:use`. Passport did not define it. Hosts finished Connect, then failed to load tools. | Real tools/list failure mode. Not on the live app until this commit deploys. Tokens minted before the scope exists still lack it. |
| `8314488` Stream tool lists for hosts that only keep SSE | Grok connects, then drops a JSON `tools/list`. Successful MCP JSON is wrapped as one SSE event when `Accept` contains `text/event-stream`. | Right diagnosis. The wrap does not run unless the client sends that Accept. Production does not have the middleware. |
| `0c93f62` Name the MCP stream event | A bare `data:` line is not a streamable-HTTP message. Hosts that only keep SSE ignore it. Response is now `event: message`. | Follow-up to the wrap. Same deploy gap. |
| `8ce96cb` Keep the MCP stream event on one line | SSE data must be one line. | Same. |
| `0001c80` Stop the MCP URL from returning an HTML error page | GET of `/mcp/revisemy` was `text/html`. Hosts that open the address before listing tools treat the connector as broken. Route plus `MethodNotAllowedHttpException` renderer return an empty 405 with `Content-Type: application/json`. | **Not deployed.** Live GET is still `text/html`. |

CI on `0001c80` is red (run 37385458367 and e2e 37385458369). Pint fails on `bootstrap/app.php` and `config/hosts.php` (style only). PHP tests fail in capture ingestion, thumbnail, guest share, and unsigned screenshot (expects 403, gets 302 from the signed-URL redirect). e2e fails on “homepage shows … before anyone gets a token”. Those failures are not the handshake, but a red `main` is a reason Laravel Cloud would skip the deploy. There is no deploy workflow in the repo. GitHub deployment records are empty.

## What Grok actually requires

From xAI’s remote MCP docs and the connector UI:

- Public HTTPS only. No localhost, no private ranges.
- Transport is Streaming HTTP or SSE. A JSON body for `tools/list` is dropped.
- Streamable HTTP events need a name. `event: message` plus `data: {jsonrpc...}`.
- Custom connector is OAuth from a URL. No header field, so a try token cannot be pasted there. Bearer still works for the API/CLI path.
- After Connect, Grok must ingest `tools/list` or the connector sits there with zero tools. This chat is in that state.

Claude and ChatGPT tolerate a JSON `tools/list`. Grok does not. That is why those two can look fine while Grok stays empty.

## What will not fix it

- Another commit on `main` that is not deployed.
- Re-adding the connector against the current production URL. The GET probe is still HTML, and Grok may reuse the cached verdict (409 / zero requests to the server, same pattern as Claude custom connectors).
- A try-token paste into the Grok custom connector form. The form has no token field. The token path is the xAI API `authorization` header, or a host that accepts headers (Cursor, Codex).
- MCP Apps / inline review. Grok ignores that resource. The agent has to paste `review_url`. Irrelevant until `create_review` exists as a tool.

## What to do next

1. Deploy `0001c80` (or later) to `revisemy.com`. Confirm with `curl -sI -H 'Accept: application/json' https://revisemy.com/mcp/revisemy` and expect `content-type: application/json`, not `text/html`.
2. Remove the Grok connector. If re-add says the URL already exists, the verdict is cached. A new path (`/mcp/revisemy-grok` aliased to the same server) is the workaround that forces a fresh probe.
3. Connect again. The first call must be POST, 401, then Connect, then `tools/list` as `event: message` SSE.
4. In a new chat, ask for the ReviseMy tools. `create_review` and `get_review` have to show up. If the connector says connected and those names are missing, the tool list was dropped again.
5. Pint `bootstrap/app.php` and `config/hosts.php` so the next green CI is not blocked on style. The capture/screenshot failures are separate.

## Code that has to be on the host

- `routes/ai.php` — GET `/mcp/revisemy` returns 405 JSON, not the HTML exception page.
- `bootstrap/app.php` — same 405 renderer, OAuth 403 back to Connect, Passport key errors as 503 instead of a generic 500. Authorize is not forced to JSON.
- `app/Http/Middleware/AuthenticateMcp.php` — missing Passport keys or a bad Bearer become 401 + `WWW-Authenticate`, not 500.
- `app/Http/Middleware/StreamMcpResponse.php` — 200 JSON-RPC on `mcp/*` becomes `event: message` SSE when the client asked for `text/event-stream`.
- `app/Providers/AppServiceProvider.php` — `Passport::tokensCan(['mcp:use' => ...])`.
- `config/hosts.php` — Grok mode is `oauth`, not `token`.
