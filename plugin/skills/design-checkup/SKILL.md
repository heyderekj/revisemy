---
name: design-checkup
description: Run a ReviseMy design checkup so a person can mark what to change on the UI, website, email or slides you just built. Use when the user asks for a design review, design checkup, visual feedback or "take a look" on something visual, or before you call visual work done.
---

# Design checkup with ReviseMy

ReviseMy gives the person a review link. They mark what matters on your capture — must fix, nice to have, question or keep — then approve or ask for changes. You read their marks as work and keep going.

## Start

1. Capture the work with exactly one source for `create_review`:
   - Local or app UI: `images` as data URLs or base64 (never `http://localhost…`). Type `ui`.
   - A public page: `capture_url: true` with `page_url`. Type `website`; ReviseMy captures desktop and phone.
   - Email: `html`. Type `email`.
   - Slides: `pdf`. Type `presentation`.
2. Call `create_review` with a short title and a line of context on what to look at.
3. Share `review_url` with the person and ask them to mark it up.

## Loop

- Poll `get_review` and follow `next_action`:
  - `wait_for_human`: keep waiting. Don't call the work done.
  - `apply_pins_then_next_pass`: fix their marks (`work_packets.pins`) in order, must fix first, and leave `keep` marks alone. Call `resolve_marks` as you go: `in_progress` while editing, `resolved` with a short note once fixed.
  - `apply_decision_note`: their note is the brief. Apply it.
  - `open_next_pass`: `create_review` again with `parent_id` and fresh captures so they can verify.
  - `done`: they approved. Stop.
- If a mark is a question with no answer, ask the person in chat. Don't invent one.

## Rules

- Their marks decide. `second_opinion` and guest notes are hints until they accept them.
- Only the person verifies a mark or approves. Never set `verified` yourself.
- The MCP server's `design_checkup_loop` prompt has the full detail, including credits and capture fallbacks.

The first call signs you in: Connect makes a free try workspace, no account needed.
