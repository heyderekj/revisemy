# Second opinion & work packets

ReviseMy’s **human marks stay authoritative**. Second opinion is optional, labeled, and never flips approve / request-changes.

**Product term:** marks (draw a mark / your marks). **API keys are unchanged** for compatibility: `work_packets.pins`, `related_pin`, action `apply_pins_then_next_pass`.

## What ships today (v1.1)

On every screenshot upload:
1. **Free checklist** runs immediately (no worker).
2. **Vision upgrade** — when `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` (or a custom OpenAI-compatible base URL) is set, vision runs **after the HTTP response** in the same app process. No separate queue worker is required — just the API key. Force a provider with `REVISEMY_VISION_PROVIDER=anthropic|openai` (default `auto`). **Only vision findings carry an `area` and render dashed regions on the screenshot.**

Agents can also act as a **design-reviewer subagent** via `add_findings` before the human opens the link. Those land in the same review UI with an `Agent` badge.

### Non-contradiction rules

1. Human marks = **intent** (`must-fix` / `wording` / `spacing` / `size` / `color` / `alignment` / `nit` / `question` / `keep` / decision). Exposed to agents as `work_packets.pins`.
2. Findings = **suggestions** only (`suggestion` / `a11y` / `polish`) — never auto-flip review status.
3. If a finding overlaps a human mark, enrich under `related_pin` — don’t invent a conflicting must-fix.
4. Second opinion is scoped to **this screenshot** (+ optional `page_url`), not the whole product.

### MCP tools

| Tool | Role |
|------|------|
| `create_review` | Screenshots + optional `page_url`; starts second opinion. Pass `parent_id` for the next checkup pass. |
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
- Dashed sky markers / areas = **vision second opinion** (checklist stays in the sidebar)
- Sidebar: **Your marks** vs **Second opinion** (upgrade note when vision keys are missing; Refresh re-runs the job)
- Taste hints that borrow Emil Kowalski’s design-engineering rules show a **BY Emil Kowalski** byline under that finding (checklist + UI vision)
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
QUEUE_CONNECTION=database   # optional — vision second opinion no longer needs a worker
REVISEMY_SECOND_OPINION=true
REVISEMY_VISION_PROVIDER=auto   # anthropic | openai | auto (prefer Claude when keyed)
ANTHROPIC_API_KEY=              # optional — Claude vision second opinion (regions on capture)
REVISEMY_ANTHROPIC_MODEL=claude-opus-4-8
OPENAI_API_KEY=                 # optional — OpenAI vision second opinion
REVISEMY_OPENAI_MODEL=gpt-4o-mini

# Free local / OpenAI-compatible vision (optional). Blank key is OK for Ollama.
# REVISEMY_VISION_PROVIDER=openai
# REVISEMY_OPENAI_BASE_URL=http://localhost:11434/v1
# REVISEMY_OPENAI_MODEL=llama3.2-vision
# OPENAI_API_KEY=
# Groq / OpenRouter / LM Studio: set REVISEMY_OPENAI_BASE_URL + their API key.
```

Local/OSS vision (e.g. Llama 3.2 Vision via Ollama) is a solid free upgrade over checklist-only, but weaker than Claude/GPT-4o at fine critique and clean JSON — severity caps, area normalize, and dedupe still apply so output degrades gracefully.

Enable a queue worker only if you rely on other queued jobs (e.g. decision webhooks). Vision second opinion runs after the HTTP response without one — set an API key and refresh.

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
