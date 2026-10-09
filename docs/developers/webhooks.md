---
title: Webhooks
description: A signed POST when the person approves or asks for changes, so a pipeline can wait on them without polling.
order: 6
icon: bolt
---

Pass a `webhook_url` to `create_review`, and ReviseMy POSTs to it when the person decides. A CI job can open a review, stop, and pick up again when the answer comes in.

## Setting one

Add `webhook_url` when you create the review, over [MCP](/docs/mcp#tool-reference) or [REST](/docs/rest-api#post-reviews). It has to be `https`. Later passes made with `parent_id` inherit it, so set it once.

## What arrives

```http
POST /hooks/revisemy HTTP/1.1
Content-Type: application/json
X-ReviseMy-Event: review.decided
X-ReviseMy-Review: 01J9Z…
X-ReviseMy-Signature: sha256=5d41402abc4b2a76b9719d911017c592…
```

```json
{
  "event": "review.decided",
  "decided_at": "2026-10-08T14:02:11+00:00",
  "review": {
    "id": "01J9Z…",
    "status": "changes_requested",
    "next_action": { "action": "apply_pins_then_next_pass", "summary": "…" },
    "work_packets": { "pins": [], "must_fix": [] }
  }
}
```

`review` is the whole review, the same object `get_review` returns. Branch on `review.status` (`approved` or `changes_requested`), and read `review.next_action` for what comes next.

## Checking the signature

`X-ReviseMy-Signature` is `sha256=` followed by an HMAC-SHA256 of the raw request body. The key is the review's secret token: the last part of its `review_url`, after `/r/`. Your code already has it from when it created the review.

Always compute it over the raw bytes, before any JSON parsing, and compare in constant time.

**Node**

```js
import crypto from 'node:crypto';

function verify(rawBody, header, reviewToken) {
  const expected = 'sha256=' + crypto.createHmac('sha256', reviewToken).update(rawBody).digest('hex');
  return header.length === expected.length
    && crypto.timingSafeEqual(Buffer.from(header), Buffer.from(expected));
}
```

**PHP**

```php
$expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $reviewToken);

if (! hash_equals($expected, (string) $request->header('X-ReviseMy-Signature'))) {
    abort(401);
}
```

**Python**

```python
import hashlib, hmac

def verify(raw_body: bytes, header: str, review_token: str) -> bool:
    expected = "sha256=" + hmac.new(review_token.encode(), raw_body, hashlib.sha256).hexdigest()
    return hmac.compare_digest(expected, header)
```

## Delivery

- Answer with any `2xx`. Anything else is a failure.
- A failure is retried up to three times, after 10 seconds, a minute and five minutes.
- Redirects aren't followed. A `3xx` counts as a failure.
- Addresses inside private networks are refused, and that's checked again before every send.
- Five failures in a row pause the webhook. `get_review` shows `webhook.paused`, the failure count and the last error, never the URL itself.

Deliveries run on the queue. If you're running your own copy, start a worker (`php artisan queue:work`) or nothing is sent.

## Gating CI on a review

A typical job:

1. Build a preview and capture it.
2. `POST /api/reviews` with the shots, a `webhook_url` pointing at your CI's webhook trigger, and a `parent_id` if this is a later pass. Post the `review_url` to the pull request.
3. Stop the job.
4. When the webhook arrives, check the signature. On `approved`, mark the check as passed. On `changes_requested`, hand `review.work_packets` to your agent and let it open the next pass.
