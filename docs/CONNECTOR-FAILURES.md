# Grok connector: failed tries, and the fix on main

Updated 2026-10-06. Production (`revisemy.com`) was still the 2026-10-03 build when this was written. A GET of `/mcp/revisemy` was `405` with `content-type: text/html`. These commits are not live until Laravel Cloud deploys `main`.

## What Grok does

Custom connector: grok.com/connectors → New Connector → Custom → paste a URL → Connect. No token field. Transport is Streaming HTTP or SSE. After OAuth it must ingest `tools/list` or the connector sits there with zero tools.

xAI's own docs server (`https://docs.x.ai/api/mcp`) answers `initialize` and `tools/list` as `application/json` when the client sends `Accept: application/json, text/event-stream`. Grok's connector has also been observed dropping a bare JSON tool list. This app does both: JSON by default, and `event: message` SSE when the client asks for `text/event-stream`.

## Failed tries (2026-10-05, before this fix)

| Commit | Try | Why it did not finish the job |
|---|---|---|
| `1ecf853` | 401 + `WWW-Authenticate` instead of a Passport 500 | Connect can start. Tools still never loaded. |
| `a23e7b2` | Stop forcing JSON on `/oauth/authorize` | Browser reached Connect. No tool list. |
| `1da90dd` | Always return `review_url` | Only matters after a tool call. |
| `aa9939b` / `6973a81` | Reconnect 403 back to Connect; skip a used approve token | Reconnect UX. Grok can still keep the old verdict. |
| `ca9a634` | Register `mcp:use` | Real tools/list failure if the scope is missing. Not on the host. |
| `8314488` / `0c93f62` / `8ce96cb` | Wrap a 200 JSON-RPC body as `event: message` SSE | Right for hosts that only keep SSE. Not on the host. |
| `0001c80` | GET must not be an HTML error page | Not deployed. Live GET was still `text/html`. |

CI on those commits was red (Pint on `bootstrap/app.php` and `config/hosts.php`, capture/screenshot tests, homepage e2e). There is no deploy workflow. Cloud did not pick `main` up.

## What this change does

- GET `/mcp/revisemy` and `/mcp/revisemy-grok` return `405` JSON (`application/json`), not an empty HTML page.
- `/mcp/revisemy-grok` is the same server on a new path, with its own protected-resource document. Grok caches a failed verdict per URL. Remove the old connector and paste `https://revisemy.com/mcp/revisemy-grok` after deploy.
- Unsigned `/shots/{id}` stays 403. Redirecting it was leaking the review token and failing `ScreenshotServingTest`. A signed link that fails still sends the human to the review.
- Connect steps tell Grok users to use the `-grok` path if `create_review` never appears.

## After deploy

```bash
curl -sI -H 'Accept: application/json' https://revisemy.com/mcp/revisemy
# content-type must be application/json, not text/html
```

Remove the Grok connector. Add a custom connector with `https://revisemy.com/mcp/revisemy-grok`. In a new chat, `create_review` and `get_review` have to be callable. If the connector says connected and those names are missing, the tool list was dropped again.

## Correction, 2026-10-06 08:30 CDT

The changelog heading 1.5.0 (2026-10-03) is not the deploy time. Connector commits are deploying. `06cfdd8` was on `revisemy.com` within minutes: `/connect` shows the new Grok step, and `/.well-known/oauth-protected-resource/mcp/revisemy-grok` returns that resource.

GET is still an empty `text/html` 405 because `laravel/mcp` `Registrar::web()` returns `response('', 405)` itself. That never throws `MethodNotAllowedHttpException`, so the exception renderer does not run, and a route registered before `Mcp::web()` is not the one answering. A middleware now rewrites any `405` on `mcp/*` to JSON.

## Fix, 2026-10-08

`d6eb7f9` passed a Closure to `$middleware->append()`, which only takes class names. That is a fatal TypeError on boot, so `composer install` failed in CI and Cloud never built it. Production stayed on `06cfdd8`, still answering GET with an empty `text/html` 405.

- The JSON 405 is now a route registered after `Mcp::web()`, for GET and DELETE on both paths. The route registered last answers, so the package's empty 405 no longer wins. The global middleware is gone.
- `StreamMcpResponse` wrapped every 200 as SSE, because every spec-compliant host sends `text/event-stream` in Accept. Only `/mcp/revisemy-grok`, or a Grok user agent on the main path, gets SSE now. Everyone else gets the package's JSON, with the rate-limit headers intact.
- Cursor and VS Code could not register: `cursor://` and `vscode://` callbacks need `config/mcp.php` `custom_schemes`.
- A signed-in browser skipped consent for any registered client. It now skips only for known assistant callbacks (`App\Support\AssistantCallback`).
- Screenshot URLs carried the owner token, so a guest page leaked it. They carry the guest token now.

Check after deploy:

```bash
curl -sI https://revisemy.com/mcp/revisemy          # 405, application/json
curl -sI -X DELETE https://revisemy.com/mcp/revisemy # 405, application/json
```
