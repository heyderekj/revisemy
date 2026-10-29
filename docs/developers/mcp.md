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

## The checkup prompt

The server offers two prompts. `design_checkup_loop` walks an agent through the whole loop: capture, `create_review`, share the link, wait with `wait_for_review`, follow `next_action`. `check_page` reviews one live page. Hosts that show prompts list them as starting points.

## Waiting and looking

Two tools keep your agent from guessing:

- `wait_for_review` holds one call until the person acts on the review (a decision, a mark, a comment, a verify), or about 45 seconds, instead of polling `get_review`. A host that sends a `progressToken` hears a heartbeat while it waits. Hosts don't support MCP Tasks yet, so this is a long-held call that works everywhere.
- `look_at_marks` returns a cropped picture of each open mark with the person's box or point drawn on it, so your agent sees what they circled, not just coordinates. Up to 5 at a time, sized for vision models.

## Marks on elements

When a shot comes with an element map, the person can click a button, a heading or a card to mark the whole thing, see its size, type, colours and spacing, and step out to the part around it. Each of those marks reaches your agent with an `element` (see [Marks](/docs/review-loop#marks)).

`capture_url` reviews have one already. For screenshots your agent takes itself, read the `revisemy://capture/elements.js` resource (or fetch `/capture/elements.js`), run it with `page.evaluate` in the same browser right after a full-page shot, and pass the result as `image_details[].elements` on `create_review`, or `elements` on `add_screenshot`, with the shot's `dpr`. A large map costs tokens to send through a tool call; the [REST API](/docs/rest-api#element-maps) takes it without that cost.

## Reviews started on the site

Someone can start a review at [`/new`](/new) without an agent: paste a link, or add screenshots, a PDF or an email. When they paste the review link to your agent, call `get_review` with the link as `id`. The review joins your agent's workspace, and every tool works on it by its `id` from then on. The first agent to open a link keeps the review; any other gets "No review with that id". Over REST, pass the token from the link: `GET /api/reviews/{token}`.

## Reviews inside the chat

In hosts that support [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview), such as Claude, ChatGPT and VS Code, `create_review` and `get_review` also render the review inline. The person marks, replies and decides without leaving the conversation. Everywhere else your agent pastes `review_url`. The loop is the same either way, and your agent should always paste the link.

The inline review also talks to the host:

- When the person approves or asks for changes, it posts a short message into the chat as them (`ui/message`), so your agent picks it up at once instead of on its next wait. Where the host doesn't take it, the review asks the person to tell their agent.
- As they mark and reply, it keeps your agent's context current (`ui/update-model-context`) without starting a turn.
- It opens full screen for marking where the host offers it, and on a phone it does so when a mark is started.
- Each `get_review` leaves another copy of the review in the chat. Only the newest stays live; older copies fold away and stop checking for changes.

Five tools exist only for that inline view: `add_mark`, `add_comment`, `decide_review`, `verify_mark` and `get_shot_elements`. They're hidden from the model, and an agent must never call them, because marking, approving and verifying stay with the person.

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
