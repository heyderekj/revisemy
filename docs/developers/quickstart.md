---
title: Quickstart
description: Your first review from a terminal. Get a try token, send a screenshot, mark it, and read back what to do next.
order: 1
icon: rocket-launch
---

This walks the whole loop with `curl`. You'll play both parts: the script that asks for a review, and the person who gives it.

## 1. Get a try token

```bash
curl -s -X POST https://revisemy.com/api/try-token
```

The response carries a `token`, when it expires, and a ready-made config for each assistant. Keep the token. It's a Bearer token for everything that follows, and it's the only thing that ties your reviews together.

```bash
export REVISEMY_TOKEN="paste-the-token-here"
```

A few try tokens can be made per address each day. If you hit the limit, use the one you have. It lasts for months.

## 2. Ask for a review

Send a title and one source. Here it's a screenshot by URL. A data URL or plain base64 works too.

```bash
curl -s -X POST https://revisemy.com/api/reviews \
  -H "Authorization: Bearer $REVISEMY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Pricing page, pass 1",
    "context": "Check the plan cards and the CTA.",
    "type": "website",
    "images": ["https://example.com/pricing.png"]
  }'
```

You get the review back with an `id`, a `review_url` and `next_action.action` set to `wait_for_human`.

## 3. Be the person

Open the `review_url` in a browser. Drag a rectangle around something and leave a mark: must fix, nit, question or keep. Then press **Changes** to ask for changes.

## 4. Read what to do

```bash
curl -s https://revisemy.com/api/reviews/REVIEW_ID \
  -H "Authorization: Bearer $REVISEMY_TOKEN"
```

Now `status` is `changes_requested` and `next_action.action` is `apply_pins_then_next_pass`. Your mark is in `work_packets.pins`, with its `id`, `severity`, `body` and the area you drew.

## 5. Report a fix

```bash
curl -s -X POST https://revisemy.com/api/reviews/REVIEW_ID/marks/resolve \
  -H "Authorization: Bearer $REVISEMY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"marks": [{"id": MARK_ID, "status": "resolved", "note": "Raised the CTA contrast."}]}'
```

Reload the review link. The mark shows your note and waits for the person to verify it. When every mark is resolved, `next_action` says `open_next_pass`. Post a new review with `"parent_id": "REVIEW_ID"` and fresh screenshots, and the loop goes round again until someone approves.

## The same thing from an agent

If your agent speaks MCP, you don't need any of the above. In Claude Code:

```bash
claude mcp add --transport http revisemy https://revisemy.com/mcp/revisemy
```

Then ask it for a design checkup. It calls `create_review`, shares the link, and follows `next_action` on its own. Every other assistant is on [Connectors](/connectors).

## Next

- [The review loop](/docs/review-loop) explains every field you just saw.
- [Webhooks](/docs/webhooks) replaces step 4's polling with a signed POST.
