---
title: Authentication
description: One try token for MCP and REST, one-click Connect for Claude and ChatGPT, and the review link as the reviewer's whole login.
order: 2
icon: ticket
---

Nobody signs up for ReviseMy. There are two kinds of credential, and the person reviewing never needs either.

## Try tokens

A try token is a Bearer token for a try workspace of your own. It works the same on the MCP server and the REST API.

```bash
curl -s -X POST https://revisemy.com/api/try-token
```

```json
{
  "token": "12|aBcD…",
  "token_expires_at": "2027-01-06T12:00:00+00:00",
  "mcp_url": "https://revisemy.com/mcp/revisemy",
  "workspace_id": "01J…",
  "cursor_config": { "mcpServers": { "revisemy": { "url": "…", "headers": { "Authorization": "Bearer …" } } } },
  "claude_code_command": "claude mcp add --transport http revisemy …"
}
```

Send it on every request:

```http
Authorization: Bearer 12|aBcD…
```

- **It owns your reviews.** `list_reviews` and `GET /api/reviews` show only the reviews made with this token's workspace. Lose it and those reviews are still reachable by their links, but not by your code.
- **It expires.** `token_expires_at` says when. On the free plan that's 90 days.
- **It carries credits.** Each workspace gets a monthly allowance, spent only by `create_review`. See [The review loop](/docs/review-loop#credits).
- **It's rate-limited at birth.** Each address can make a few try tokens per hour and per day. Beyond that the endpoint answers `429`. Reuse the token you have.

The [Connect page](/connect) makes one for you with copy-ready setup for every assistant, if you'd rather not use `curl`.

## Connect (OAuth)

Claude, ChatGPT and Grok add a custom connector from just the address. When they do, they find ReviseMy's OAuth discovery documents, register themselves, and send you to a one-click Connect screen. There's no account behind it. Connecting makes a try workspace, and the assistant holds the token.

What a client reads:

| Document | Says |
|---|---|
| `/.well-known/oauth-protected-resource/mcp/revisemy` | The resource, its authorization server, and the one scope, `mcp:use` |
| `/.well-known/oauth-authorization-server` | Where to register, authorize and swap a code for a token |

If you're building an MCP client, the standard MCP authorization flow is all you need. If your client can't do OAuth, a try token in the `Authorization` header works on the same address.

## The review link

`review_url` (`/r/{token}`) is the reviewer's whole login. Whoever has it can mark, approve and ask for changes, so share it like you'd share a document link.

- The secret in that URL also signs the [webhook](/docs/webhooks) for that review.
- `guest_share_url` is a second link for other people. Guests leave suggestions, not marks, and the owner accepts or dismisses them.
- Reviews expire. `expires_at` in the payload says when, and after that `next_action` is `expired`.

## Rate limits

| What | Limit |
|---|---|
| MCP requests | 120 a minute per client |
| `POST /api/reviews` | 30 a minute per token |
| Screenshots and findings | 60 a minute per token |
| `marks/resolve` | 120 a minute per token |
| `second-opinion` | 30 a minute per token |

A limit answers `429`. Back off and try again in a minute.
