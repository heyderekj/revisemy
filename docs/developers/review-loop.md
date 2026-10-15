---
title: The review loop
description: What a review holds, what next_action tells your agent, how a mark moves from open to verified, and how passes stack up.
order: 4
icon: arrow-path
---

Every review moves the same way. Your agent sends the work. A person marks it and decides. Your agent fixes what was marked, reports each fix, and opens the next pass. It ends when the person approves.

## A review

`create_review`, `get_review` and the REST endpoints all return the same object. The parts that matter most:

| Field | What it is |
|---|---|
| `id` | The review's public id. Pass it to every other call. |
| `status` | `pending`, `changes_requested`, `approved` or `expired` |
| `review_url` | The link for the person. Always share it. |
| `board_url` | The same review as a board, grouped by where each mark stands |
| `guest_share_url` | A link for other people, who leave suggestions rather than marks |
| `pass` / `parent_id` | Which pass this is, and the one before it |
| `next_action` | What your agent does now. See below. |
| `work_packets` | The marks, sorted into work. See below. |
| `loop` | Counts: must-fix, nits, questions, outstanding, awaiting verification |
| `decision_note` | Anything the person wrote when they decided |
| `updated_at` | Changes whenever the person does anything. If it hasn't moved, skip the rest of the poll. |
| `expires_at` | When the link stops working |

## next_action

Your agent shouldn't have to work out what to do from the status. `next_action.action` says it, and `next_action.summary` says it in a sentence.

<!-- generated:next-actions -->
The table is generated from `Review::NEXT_ACTIONS` on the site. Read it at [revisemy.com/docs/review-loop](https://revisemy.com/docs/review-loop#next-action).
<!-- /generated -->

While it's `wait_for_human`, call `wait_for_review` with `since` set to `updated_at` (`next_action.wait_with` has both). It holds until the person does anything, or about 45 seconds, then returns the same record as `get_review` plus `changed`. Call it again while `changed` is `false`. A host that sends a `progressToken` hears a heartbeat while it waits. Over REST it's `GET /api/reviews/{id}/wait?since=…`. If your agent can't hold a call that long, poll `get_review` about every 30 seconds instead, or set a [webhook](/docs/webhooks) and don't poll at all.

In hosts that show the review inline (Claude, ChatGPT, VS Code), the person's decision also arrives in the chat as their message, such as "I asked for changes on Pricing page, pass 2: 3 marks to fix. Go ahead." The inline review keeps your agent's context current as they mark and reply, too.

## Marks

Every mark the person leaves is in `work_packets.pins`. The key says pins for compatibility, but each one is a mark.

| Field | What it is |
|---|---|
| `id` | Use this with `resolve_marks` and `reply_to_marks` |
| `number` | The M1, M2… the person sees |
| `severity` | `must-fix`, `nit`, `question` or `keep`, plus tweak kinds such as `wording` or `spacing` |
| `body` | What the person wrote |
| `area` | The rectangle they drew: `x`, `y`, `w`, `h` as fractions of the screenshot, 0 to 1 |
| `suggested_copy` | Exact words to use. When it's set, use them as they are. |
| `question_answer` | The answer to a question mark, once the person gives one |
| `status` | `open`, `in_progress`, `resolved` or `verified` |
| `comments` | The latest comments on the mark, each with `author_type`: `owner`, `guest` or `agent` |
| `timeline` | Everything that happened on the mark, oldest first: `kind` (`marked`, `started`, `resolved`, `reopened`, `verified`, `comment`), `actor` (`owner`, `guest`, `agent`), `body`, `after_screenshot_url` and `at` |
| `awaiting_reply` | `true` when a person spoke last and your agent hasn't answered |
| `resolved_by` | Who last resolved it: `agent`, or `owner` when the person resolved it on the board |
| `source` | `human`, or where an accepted hint came from: `guest`, `checklist`, `vision`, `agent` |

The same marks are also split into `must_fix`, `nits`, `questions`, `tweaks`, `keeps` and `awaiting_verification`, so your agent can work in order. Fix must-fix first, then nits. Leave anything marked keep alone, and ask before inventing an answer to an open question.

## A mark's life

1. **Open.** The person left it.
2. **In progress.** Your agent called `resolve_marks` with `status: "in_progress"`.
3. **Resolved.** Your agent called `resolve_marks` with `status: "resolved"` and a `note` saying what changed. Attach an `after_image` of the fixed area, and the person sees a before and after.
4. **Verified.** The person checked it. Or they reopened it, and it's open again.

Only the person verifies. There's no way for an agent to mark its own work verified, on purpose.

`resolve_marks` takes up to 50 marks at once. Check `skipped` in the response: a mark listed there didn't change, with the reason. Resolving only works while the review's status is `changes_requested`.

Every step lands on the mark's timeline with who took it, and the person sees it on the board, the review page, the guest link and inline in the chat. Your agent's steps carry an **Agent** label.

## Replying on a mark

Your agent can answer in a mark's thread with `reply_to_marks`: answer a question, say why it didn't change something, or ask before guessing. It takes up to 20 replies, each `{id, body}`, while the review waits on the person or after they ask for changes. A reply never changes the mark, so progress still goes through `resolve_marks`.

Replies arrive in `work_packets.pins[].comments` with `author_type: "agent"`. The person and any guests see them with an Agent tag, the same tag that marks your agent's notes, the statuses it set, its after images and its findings. `status_by` on each pin says whether your agent or the person set the current status. If the owner turned comments off, replies come back in `skipped_replies` as `comments_off`.

Each pin also has a `timeline`: everything that happened on the mark, in order. When `awaiting_reply` is `true`, a person spoke last (they asked something, or reopened the mark with a comment) and is waiting on your agent. `loop.awaiting_reply_count` says how many marks are waiting, and `next_action` names them. To fix and explain in one step, add `reply` to a mark in `resolve_marks`. Over REST, replies are `POST /api/reviews/{id}/marks/reply` with `{"replies": [{"id": 12, "body": "…"}]}`.

## Passes

When every mark is resolved, `next_action` becomes `open_next_pass`. Call `create_review` again with `parent_id` set to this review and fresh captures. The new pass inherits the type and the webhook. It also shows the person what changed since the last one.

Marks the person reopens on an earlier pass arrive in `work_packets.carried_over`. They're work for this pass too.

## Hints, not marks

Two things can add notes that aren't the person's:

- **The second opinion.** A checklist for the review's type runs once per review and sits on the first screenshot. With a vision key set on the server, it can also draw dashed regions. `request_second_opinion` runs it again.
- **Your agent.** `add_findings` drops `suggestion`, `a11y` or `polish` notes into the review before the person looks. It can't add must-fix.

Both arrive in `work_packets.second_opinion`. They're hints only. When the person accepts one it becomes a mark, with `source` saying where it came from. Until then, don't treat them as work.

## Credits

Creating a review spends credits, depending on its source:

<!-- generated:costs -->
The cost table is generated from `config/billing.php` on the site. Read it at [revisemy.com/docs/review-loop](https://revisemy.com/docs/review-loop#credits).
<!-- /generated -->

`get_billing` (or `GET /api/billing`) shows what's left and when it refills.
