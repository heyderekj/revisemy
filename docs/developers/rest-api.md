---
title: REST API
description: The same reviews over plain HTTP, for scripts and CI. Every endpoint, with a curl example.
order: 5
icon: command-line
---

The REST API mirrors the MCP tools for code that isn't an agent. It takes the same fields and returns the same review object, so [MCP server](/docs/mcp#tool-reference) is the reference for what each field means.

- Base URL: `https://revisemy.com/api`
- Auth: `Authorization: Bearer YOUR_TRY_TOKEN` on everything except `POST /try-token`. See [Authentication](/docs/authentication).
- Send and expect JSON, with `Accept: application/json` so errors come back as JSON too.

## Status codes

| Code | Means |
|---|---|
| `200` / `201` | Done. The body is the review, or what the endpoint describes. |
| `401` | The token is missing, wrong or expired. |
| `402` | Not enough credits. The body says how many you need and have. |
| `404` | No review with that id for this token. |
| `422` | The request didn't validate, or the review isn't in a state for it. `message` says which. |
| `429` | Too many requests. See [rate limits](/docs/authentication#rate-limits). |

## POST /try-token

Makes a try workspace and its token. No auth.

```bash
curl -s -X POST https://revisemy.com/api/try-token
```

## POST /reviews

Starts a review. Send a `title` and exactly one source: `images`, `capture_url` with `page_url`, `pdf`, or `html`.

```bash
curl -s -X POST https://revisemy.com/api/reviews \
  -H "Authorization: Bearer $REVISEMY_TOKEN" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "title": "Launch email",
    "type": "email",
    "html": "<html>…</html>",
    "webhook_url": "https://ci.example.com/hooks/revisemy"
  }'
```

| Field | Notes |
|---|---|
| `title` | Required. Up to 160 characters. |
| `context` | What the person should look at on this pass |
| `type` | `ui` (default), `website`, `presentation` or `email`. It sets the second opinion's checklist. |
| `images` | 1 to 5 screenshots: https image URLs, data URLs or base64 |
| `capture_url` + `page_url` | `true` and a public URL: ReviseMy captures desktop, mobile and tablet |
| `pdf` | An https URL or base64. One shot per page, up to 5. |
| `html` | An email's HTML, rendered at mail-client width |
| `parent_id` | The previous pass, when opening the next one |
| `webhook_url` | An https URL to POST to when the person decides. See [Webhooks](/docs/webhooks). |
| `design_rules` | Your project's `DESIGN.md` (or other design rules) as Markdown, up to 20,000 characters. The second opinion checks each shot against them first. Later passes inherit them. |

Returns `201` with the review.

## GET /reviews/{id}

The review, with its marks and `next_action`. This is what you poll.

```bash
curl -s https://revisemy.com/api/reviews/$REVIEW_ID -H "Authorization: Bearer $REVISEMY_TOKEN"
```

## GET /reviews

The 20 latest reviews for this token, as summaries: status, pass, outstanding and awaiting-verification counts, and `next_action`. Get a review by id for its marks.

```bash
curl -s https://revisemy.com/api/reviews -H "Authorization: Bearer $REVISEMY_TOKEN"
```

## POST /reviews/{id}/screenshots

Adds a shot to an open review. Returns the review.

```bash
curl -s -X POST https://revisemy.com/api/reviews/$REVIEW_ID/screenshots \
  -H "Authorization: Bearer $REVISEMY_TOKEN" -H "Content-Type: application/json" \
  -d '{"image": "data:image/png;base64,iVBORw0…"}'
```

## POST /reviews/{id}/marks/resolve

Reports progress on marks while you fix them. Only works while the status is `changes_requested`.

```bash
curl -s -X POST https://revisemy.com/api/reviews/$REVIEW_ID/marks/resolve \
  -H "Authorization: Bearer $REVISEMY_TOKEN" -H "Content-Type: application/json" \
  -d '{
    "marks": [
      {"id": 41, "status": "resolved", "note": "Tightened the heading spacing.", "after_image": "https://example.com/after.png"},
      {"id": 42, "status": "in_progress"}
    ]
  }'
```

Returns `updated` (a count), `skipped` (marks that didn't change, and why) and the review. If nothing changed, it's a `422` with `skipped`.

## POST /reviews/{id}/findings

Adds hints before the person looks. Up to 20 at once, each with a `body` and an optional `severity` (`suggestion`, `a11y` or `polish`), `screenshot_index`, `area` and `related_pin`.

```bash
curl -s -X POST https://revisemy.com/api/reviews/$REVIEW_ID/findings \
  -H "Authorization: Bearer $REVISEMY_TOKEN" -H "Content-Type: application/json" \
  -d '{"findings": [{"body": "The footer links are under 4.5:1 contrast.", "severity": "a11y"}]}'
```

## POST /reviews/{id}/second-opinion

Runs the second opinion again, on every shot or on one by `screenshot_index`. Returns `queued` and the review.

```bash
curl -s -X POST https://revisemy.com/api/reviews/$REVIEW_ID/second-opinion \
  -H "Authorization: Bearer $REVISEMY_TOKEN"
```

## GET /billing

Credits left, the plan, what each source costs, and when credits refill.

```bash
curl -s https://revisemy.com/api/billing -H "Authorization: Bearer $REVISEMY_TOKEN"
```
