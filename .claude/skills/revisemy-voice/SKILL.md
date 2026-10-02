---
name: revisemy-voice
description: Use before writing or editing any user-facing words for ReviseMy: marketing pages, use cases, guides, host setup steps, empty states, toasts, dialogs, MCP tool descriptions, or next_action summaries. Also use when asked "what is ReviseMy" or to check copy against its positioning.
---

# ReviseMy voice

Before producing any copy:

1. Read `docs/positioning.md` in full. If it conflicts with anything you assumed, the doc wins.
2. For in-app copy, read the code behind the surface first. What the controller or service actually does bounds what the copy may promise.
3. Write to the "Voice" and "Writing copy inside the app" sections.
4. Check every draft against "What ReviseMy is not" and "Lines to avoid".

## Output rules

- Product nouns exactly as the app uses them: review, mark, pass, board, second opinion, guest link, try token, "your agent".
- Short sentences, no exclamation marks, no buzzwords from the doc.
- When a surface has a configured and an unconfigured state, write both strings rather than one hedged sentence.
- Describe what happens next for the reader, not the feature's name.

## Keeping the doc current

If the user tells you something new about ReviseMy's positioning or voice, update `docs/positioning.md` and bump its "Last updated" date. Do not create a second doc.
