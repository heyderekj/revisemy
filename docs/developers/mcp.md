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

## Community skills

Describe what you want reviewed, for example, “Are our failed-payment messages clear enough to help someone recover? Put your findings on a ReviseMy review.” The agent can call `get_review_skill` with `focus: copy` to load Better Writing and a workflow for gathering evidence and posting findings. Other supported focuses are `typography`, `motion`, and `variants`. The agent can skip this tool for a general review.

Pass `skill` when the user names one, or call the tool without arguments to list the options. When available, the tool supplies the author's instructions at a fixed revision, source, and license. For motion and variants, it supplies ReviseMy guidance when the agent selects the focus, and loads the author's opt-in skill when the user asks for it. If the original instructions are unavailable, the agent can use an installed copy or ReviseMy's guidance and tell you what it used.

The tool suggests the relevant next calls. Use `get_review` to work on an existing review or `create_review` for a new one when the task calls for it. Use `add_findings` when you have observed issues to share. Findings are suggestions the person can accept or dismiss.

## Waiting and looking

Two tools keep your agent from guessing:

- `wait_for_review` holds one call until the person acts on the review (a decision, a mark, a comment, a verify), or about 45 seconds, instead of polling `get_review`. A host that sends a `progressToken` hears a heartbeat while it waits. Hosts don't support MCP Tasks yet, so this is a long-held call that works everywhere.
- `look_at_marks` returns a cropped picture of each open mark with the person's box or point drawn on it, so your agent sees what they circled, not just coordinates. Up to 5 at a time, sized for vision models.

## Marks on elements

When a shot comes with an element map, the person can click a button, a heading or a card to mark the whole thing, see its size, type, colours and spacing, and step out to the part around it. Each of those marks reaches your agent with an `element` (see [Marks](/docs/review-loop#marks)).

URL capture can include an element map from the same browser page as the screenshot. If the provider only returns image bytes, a map from a separate load is approximate and can't drive element marks. For screenshots your agent takes itself, read the `revisemy://capture/elements.js` resource (or fetch `/capture/elements.js`), run it with `page.evaluate` in the same browser right after a full-page shot, and pass the result as `image_details[].elements` on `create_review`, or `elements` on `add_screenshot`, with the shot's `dpr`. For signed-in pages and interactions, use `/capture/states.mjs` with your existing browser and pass the returned `images` and `image_details` to `create_review`. The [REST API examples](/docs/rest-api#element-maps) show how to capture a menu before and after opening it.

## Reviews started on the site

Someone can start a review at [`/new`](/new) without an agent: paste a link, or add screenshots, a PDF or an email. When they paste the review link to your agent, call `get_review` with the link as `id`. The review joins your agent's workspace, and every tool works on it by its `id` from then on. The first agent to open a link keeps the review; any other gets "No review with that id". Over REST, pass the token from the link: `GET /api/reviews/{token}`.

## Reviews inside the chat

In hosts that support [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview), such as Claude, ChatGPT and VS Code, `create_review` and `get_review` also render the review inline. The person marks, replies and decides without leaving the conversation. Everywhere else your agent pastes `review_url`. The loop is the same either way, and your agent should always paste the link.

The inline review also talks to the host:

- When the person approves or asks for changes, it posts a short message into the chat as them (`ui/message`), so your agent picks it up at once instead of on its next wait. Where the host doesn't take it, the review asks the person to tell their agent.
- As they mark and reply, it keeps your agent's context current (`ui/update-model-context`) without starting a turn.
- It opens full screen for marking where the host offers it, and on a phone it does so when a mark is started.
- Each `get_review` leaves another copy of the review in the chat. Only the newest stays live; older copies fold away and stop checking for changes.

Six tools exist only for that inline view: `add_mark`, `add_comment`, `set_comment_reaction`, `decide_review`, `verify_mark` and `get_shot_elements`. They're hidden from the model, and an agent must never call them, because marking, approving and verifying stay with the person.

## Tool reference

These are the tools your agent sees, with the descriptions it reads. Review operations are also documented in the [REST API](/docs/rest-api).

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

A failed `capture_url` still opens the review. The reply names failed viewports and keeps the successful shots. Before sharing, replace failed placeholders via `add_screenshot` with the review `id` and the failed shot's `replace_id`. For partial captures, use the quality message to decide what to verify or recapture before marking it.

Anything else is a plain sentence meant for your agent to read and act on.
