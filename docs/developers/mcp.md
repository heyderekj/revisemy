---
title: MCP server
description: The address, how clients connect, what they can discover, and every tool your agent can call, generated from the server itself.
order: 3
icon: puzzle-piece
---

ReviseMy is a remote MCP server over streamable HTTP. One address serves every client:

```text
https://revisemy.com/mcp/revisemy
```

POST JSON-RPC to it. A `GET` answers a JSON `405`, which is expected. Authenticate with a [try token](/docs/authentication#try-tokens) as a Bearer header, or let the client [connect with OAuth](/docs/authentication#connect-oauth).

## Adding it

Most assistants have a one-step setup on [Connectors](/connectors). For anything else, the config is the same shape:

```json
{
  "mcpServers": {
    "revisemy": {
      "url": "https://revisemy.com/mcp/revisemy",
      "headers": { "Authorization": "Bearer YOUR_TRY_TOKEN" }
    }
  }
}
```

Claude Code also has a plugin that adds the server and a `design-checkup` skill:

```text
/plugin marketplace add heyderekj/revisemy
/plugin install revisemy@revisemy
```

## Discovery

| Document | What it's for |
|---|---|
| [`/.well-known/mcp/server-card.json`](/.well-known/mcp/server-card.json) | Name, version, endpoint, tools and prompts, read from the server |
| `/.well-known/oauth-protected-resource/mcp/revisemy` | OAuth resource metadata for clients that connect by signing in |
| [`/llms.txt`](/llms.txt) | A short index of the site and the tools, for agents |
| `server.json` in the repo | The entry in the MCP registry, `io.github.heyderekj/revisemy` |

## Prompts

- `design_checkup_loop` walks an agent through the whole loop: capture, `create_review`, share the link, poll `get_review`, follow `next_action`.
- `check_page` takes a `url` (and an optional `focus`) and starts a review of one live page.

Hosts that show prompts list both as starting points.

## Your DESIGN.md

If your project has a `DESIGN.md`, the Markdown file that tells coding agents your design system, have your agent pass its text as `design_rules` on the first `create_review`. Later passes keep it. The second opinion then checks every shot against your rules before anything else:

- The free checklist turns your firmest rules (never, always, must, avoid…) into checks.
- With vision on, hints that break a rule start with `DESIGN.md:` and name it.

The review shows a `DESIGN.md` chip so you know. `get_review` returns `design_rules` with where they came from and how many rules were read, never the file itself. When one of your marks states a lasting preference, the agent is asked to propose adding it to `DESIGN.md`, and to ask you before editing it.

No repo? Paste your rules once under **Design rules** on [Your reviews](/reviews) and every review without its own uses them.

## Reviews inside the chat

In hosts that support [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview), such as Claude and VS Code, `create_review` and `get_review` also render the review inline. The person marks and decides without leaving the conversation. Everywhere else your agent pastes `review_url`. The loop is the same either way, and your agent should always paste the link.

Three tools exist only for that inline view: `add_mark`, `decide_review` and `verify_mark`. They're hidden from the model, and an agent must never call them, because approving and verifying stay with the person.

## Tool reference

These are the tools your agent sees, with the descriptions it reads. Each one's REST twin is on [REST API](/docs/rest-api).

<!-- generated:tools -->
The tool list is generated from the server on the site. Read it at [revisemy.com/docs/mcp](https://revisemy.com/docs/mcp#tool-reference), or in `app/Mcp/Tools`.
<!-- /generated -->

## Errors

A tool that can't do what was asked answers with an MCP error whose message starts with a bracketed code your agent can branch on:

| Code | What to do |
|---|---|
| `[capture_not_configured]` | This server can't render `html`. Retry once with `images` as data URLs. |
| `[capture_provider_failed]` | Rendering failed. Retry once with `images`. |
| `[insufficient_credits]` | Out of credits. Call `get_billing` for when they refill. |

A failed `capture_url` doesn't error. The review still opens, so there's always a link, but its shot is a blank placeholder (`meta.origin` is `capture_failed`) and the reply names the failure. Your agent should add the page with `add_screenshot`, desktop and mobile, before it shares the link.

Anything else is a plain sentence meant for your agent to read and act on.
