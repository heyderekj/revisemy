---
title: Build on ReviseMy
nav: Overview
description: Wire a person's review into your agent, script or pipeline. An MCP server, a small REST API and a signed webhook, all behind one try token.
order: 0
icon: code-bracket
---

ReviseMy puts a person between "the agent made it" and "it ships". Your code sends a picture of the work, gets back a review link, and waits. Someone opens the link, marks what matters, then approves or asks for changes. Your code reads those marks as work, fixes them, and opens the next pass until it's approved.

There's no account and no SDK. Everything below is plain HTTP.

## Three ways in

- **MCP.** Most people never write code for it: your agent adds `https://revisemy.com/mcp/revisemy` as a connector and calls the tools itself. See [MCP server](/docs/mcp).
- **REST.** For scripts, CI jobs and anything that isn't an agent. The same reviews, the same payloads. See [REST API](/docs/rest-api).
- **Webhooks.** So a pipeline can wait on a person without polling. See [Webhooks](/docs/webhooks).

All three use the same try token. See [Authentication](/docs/authentication).

## Where to start

1. [Quickstart](/docs/quickstart): your first review from a terminal, in about five minutes.
2. [The review loop](/docs/review-loop): what's in a review, what `next_action` means, and how a mark moves from open to verified.
3. The reference pages above, once you know what you're building.

## Every page as markdown

Each page here has a markdown twin for agents: add `.md` to the address, as in [`/docs/mcp.md`](/docs/mcp.md). [`/llms-full.txt`](/llms-full.txt) has the whole site in one file. The source is in [`docs/developers`](https://github.com/heyderekj/revisemy/tree/main/docs/developers) on GitHub, and pull requests are welcome.

## Running your own copy

ReviseMy is open source under the [O'Saasy License](https://osaasy.dev/). Setting it up locally, deploying to Laravel Cloud and running the tests are covered in the [README](https://github.com/heyderekj/revisemy#local-development).
