# Second opinion & work packets

ReviseMy’s **human pins stay authoritative**. Second opinion is optional, labeled, and never flips approve / request-changes.

## What ships today (v1.1)

On every screenshot upload, Laravel Cloud queues `GenerateSecondOpinionJob`:

1. **Free checklist** — hierarchy, contrast, spacing, mobile/desktop heuristics from dimensions + review context keywords.
2. **OpenAI vision upgrade** — when `OPENAI_API_KEY` is set, the same job merges vision findings (`suggestion` / `a11y` / `polish` only).

Agents can also act as a **design-reviewer subagent** via `add_findings` before the human opens the link. Those land in the same review UI with an `Agent` badge.

### Non-contradiction rules

1. Human pins = **intent** (`must-fix` / `nit` / decision).
2. Findings = **suggestions** only (`suggestion` / `a11y` / `polish`) — never auto-flip review status.
3. If a finding overlaps a human pin, enrich under `related_pin` — don’t invent a conflicting must-fix.
4. Second opinion is scoped to **this screenshot** (+ optional `page_url`), not the whole product.

### MCP tools

| Tool | Role |
|------|------|
| `create_review` | Screenshots + optional `page_url`; auto-queues second opinion |
| `add_findings` | Subagent path — push critique into the open review |
| `request_second_opinion` | Re-queue checklist (+ vision if keyed) |
| `get_review` | Work packets: `work_packets.pins` first, then `work_packets.second_opinion` + `guidance` |

### Review UI

- Solid rose/amber markers = **your pins**
- Dashed sky markers / areas = **second opinion**
- Sidebar: **Your pins** vs **Second opinion** (Refresh re-queues the Cloud job)

### Agent payload shape

```json
{
  "guidance": "Apply human pins first…",
  "work_packets": {
    "pins": [{ "number": 1, "severity": "must-fix", "body": "…", "screenshot_index": 0 }],
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

- [design-review-skill](https://github.com/aslanmazhidov/design-review-skill)
- [ui-craft](https://github.com/educlopez/ui-craft)
- [OmniParser](https://github.com/microsoft/OmniParser) / [UIPE](https://github.com/dirkknibbe/uipe) / [Playwright MCP](https://github.com/microsoft/playwright-mcp)
