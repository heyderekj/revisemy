# Second opinion & work packets

ReviseMy’s **human marks stay authoritative**. Second opinion is optional, labeled, and never flips approve / request-changes.

**Product term:** marks (draw a mark / your marks). **API keys are unchanged** for compatibility: `work_packets.pins`, `related_pin`, action `apply_pins_then_next_pass`.

## What ships today (v1.1)

On every screenshot upload, Laravel Cloud queues `GenerateSecondOpinionJob`:

1. **Free checklist** — hierarchy, contrast, spacing, mobile/desktop heuristics, plus Emil Kowalski taste checks (press feedback, soft depth vs hard borders; motion hints when context mentions modals/drawers/toasts).
2. **OpenAI vision upgrade** — when `OPENAI_API_KEY` is set, the same job merges vision findings (`suggestion` / `a11y` / `polish` only), guided by the same taste lens.

Agents can also act as a **design-reviewer subagent** via `add_findings` before the human opens the link. Those land in the same review UI with an `Agent` badge.

### Non-contradiction rules

1. Human marks = **intent** (`must-fix` / `wording` / `spacing` / `size` / `color` / `alignment` / `nit` / `question` / `keep` / decision). Exposed to agents as `work_packets.pins`.
2. Findings = **suggestions** only (`suggestion` / `a11y` / `polish`) — never auto-flip review status.
3. If a finding overlaps a human mark, enrich under `related_pin` — don’t invent a conflicting must-fix.
4. Second opinion is scoped to **this screenshot** (+ optional `page_url`), not the whole product.

### MCP tools

| Tool | Role |
|------|------|
| `create_review` | Screenshots + optional `page_url`; auto-queues second opinion. Pass `parent_id` for the next checkup pass. |
| `add_findings` | Subagent path — push critique into the open review |
| `request_second_opinion` | Re-queue checklist (+ vision if keyed) |
| `get_review` | Work packets + **`next_action`** (`wait_for_human` / `apply_pins_then_next_pass` / `done`) |

Prompt: **`design_checkup_loop`** — teaches agents the full cycle.

### The loop

1. Agent: `create_review` → share `review_url`
2. Human: mark + approve / request changes
3. Agent: `get_review` → follow `next_action`
4. If changes requested: apply marks (`work_packets.pins`) → `create_review` with `parent_id` + new screenshots
5. Repeat until approved

### Review UI

- Solid rose selection rectangles = **your marks**
- Dashed sky markers / areas = **second opinion**
- Sidebar: **Your marks** vs **Second opinion** (Refresh re-queues the Cloud job)
- After a decision: clear copy for “next pass” vs “loop complete”

### Agent payload shape

```json
{
  "pass": 1,
  "next_action": { "action": "wait_for_human", "summary": "…" },
  "guidance": "Apply human marks first (work_packets.pins)…",
  "work_packets": {
    "pins": [{ "number": 1, "severity": "must-fix", "body": "…", "screenshot_index": 0 }],
    "must_fix": [],
    "nits": [],
    "second_opinion": [{ "source": "checklist", "severity": "a11y", "body": "…", "area": { "x": 0.1, "y": 0.2, "w": 0.3, "h": 0.1 }, "screenshot_index": 0 }]
  }
}
```

### Cloud env

```
QUEUE_CONNECTION=database   # or Cloud’s queue
REVISEMY_SECOND_OPINION=true
OPENAI_API_KEY=             # optional — upgrades checklist with vision
REVISEMY_OPENAI_MODEL=gpt-4o-mini
```

Enable a **queue worker** on Laravel Cloud so jobs run after upload.

## Roadmap (not blocking contest)

| Step | Idea |
|------|------|
| v1.2 | If `page_url` is set, attach selector / a11y role via Playwright MCP / UIPE |
| v1.3 | Optional OmniParser pass for element labels when no DOM is available |

Research references (implementer skills for consumer repos):

- [emilkowalski/skills](https://github.com/emilkowalski/skills) — design-engineering taste (animation decisions, press feedback, depth, reduced motion). Install: `npx skills@latest add emilkowalski/skills`. ReviseMy’s free checklist + vision prompt already borrow these rules as **hints only**.
- [design-review-skill](https://github.com/aslanmazhidov/design-review-skill)
- [ui-craft](https://github.com/educlopez/ui-craft)
- [OmniParser](https://github.com/microsoft/OmniParser) / [UIPE](https://github.com/dirkknibbe/uipe) / [Playwright MCP](https://github.com/microsoft/playwright-mcp)
