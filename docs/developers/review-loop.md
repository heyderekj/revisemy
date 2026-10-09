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

While it's `wait_for_human`, poll `get_review` about every 30 seconds, or set a [webhook](/docs/webhooks) and don't poll at all.

## Marks

Every mark the person leaves is in `work_packets.pins`. The key says pins for compatibility, but each one is a mark.

| Field | What it is |
|---|---|
| `id` | Use this with `resolve_marks` |
| `number` | The M1, M2… the person sees |
| `severity` | `must-fix`, `nit`, `question` or `keep`, plus tweak kinds such as `wording` or `spacing` |
| `body` | What the person wrote |
| `area` | The rectangle they drew: `x`, `y`, `w`, `h` as fractions of the screenshot, 0 to 1 |
| `suggested_copy` | Exact words to use. When it's set, use them as they are. |
| `question_answer` | The answer to a question mark, once the person gives one |
| `status` | `open`, `in_progress`, `resolved` or `verified` |
| `comments` | The latest replies on the mark |
| `source` | `human`, or where an accepted hint came from: `guest`, `checklist`, `vision`, `agent` |

The same marks are also split into `must_fix`, `nits`, `questions`, `tweaks`, `keeps` and `awaiting_verification`, so your agent can work in order. Fix must-fix first, then nits. Leave anything marked keep alone, and ask before inventing an answer to an open question.

## A mark's life

1. **Open.** The person left it.
2. **In progress.** Your agent called `resolve_marks` with `status: "in_progress"`.
3. **Resolved.** Your agent called `resolve_marks` with `status: "resolved"` and a `note` saying what changed. Attach an `after_image` of the fixed area, and the person sees a before and after.
4. **Verified.** The person checked it. Or they reopened it, and it's open again.

Only the person verifies. There's no way for an agent to mark its own work verified, on purpose.

`resolve_marks` takes up to 50 marks at once. Check `skipped` in the response: a mark listed there didn't change, with the reason. Resolving only works while the review's status is `changes_requested`.

## Passes

When every mark is resolved, `next_action` becomes `open_next_pass`. Call `create_review` again with `parent_id` set to this review and fresh captures. The new pass inherits the type and the webhook. It also shows the person what changed since the last one.

Marks the person reopens on an earlier pass arrive in `work_packets.carried_over`. They're work for this pass too.

## Hints, not marks

Two things can add notes that aren't the person's:

- **The second opinion.** A checklist for the review's type runs on every screenshot. With a vision key set on the server, it can also draw dashed regions. `request_second_opinion` runs it again.
- **Your agent.** `add_findings` drops `suggestion`, `a11y` or `polish` notes into the review before the person looks. It can't add must-fix.

Both arrive in `work_packets.second_opinion`. They're hints only. When the person accepts one it becomes a mark, with `source` saying where it came from. Until then, don't treat them as work.

## Credits

Creating a review spends credits, depending on its source:

<!-- generated:costs -->
The cost table is generated from `config/billing.php` on the site. Read it at [revisemy.com/docs/review-loop](https://revisemy.com/docs/review-loop#credits).
<!-- /generated -->

`get_billing` (or `GET /api/billing`) shows what's left and when it refills.
