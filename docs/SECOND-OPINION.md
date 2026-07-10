# Second opinion & UI grounding (research)

ReviseMy’s human pins stay authoritative. A **second opinion** is optional, labeled, and never overrides approve / request-changes. UI grounding tools help the *implementing* agent map pins to real elements.

## Design skills (screenshot-specific critique)

| Project | Fit for ReviseMy | Notes |
|---------|------------------|--------|
| [aslanmazhidov/design-review-skill](https://github.com/aslanmazhidov/design-review-skill) | Strong | Playwright screenshots → structured audit (type, contrast, rhythm, hierarchy) + concrete CSS fixes. Vision-first, not code-guessing. |
| [educlopez/ui-craft](https://github.com/educlopez/ui-craft) | Strong | `design-reviewer` + `a11y-auditor` agents; severity-tagged findings; anti-slop / score gates. Good as an MCP-side “opinion” layer. |
| [edrouhardmicrosoft/agent-canvas-skills](https://github.com/edrouhardmicrosoft/agent-canvas-skills) | Strong | Annotated screenshots, interactive element picker, fix task lists. Closest to pin + annotate UX. |
| [kozz36/frontend-designer-skill](https://github.com/kozz36/frontend-designer-skill) | Medium | Tokens, CSS layers, WCAG — better for *implementation* taste than per-screenshot critique. |
| [carmahhawwari/ui-design-brain](https://github.com/carmahhawwari/ui-design-brain) | Medium | 60+ component patterns — helps agents build correctly after pins, not critique the shot. |
| [keg-flair/cursor-designer-agents](https://github.com/keg-flair/cursor-designer-agents) | Medium | Screenshot-oriented designer skills for Cursor. |

**Recommended combo for ReviseMy:**  
`design-review-skill` *or* `ui-craft:design-reviewer` for the second opinion · `frontend-designer-skill` / `ui-design-brain` as implementer skills in the consumer’s repo.

### Non-contradiction rules

1. Human pins = **intent** (`must-fix` / `nit` / decision).  
2. Skill output = **suggestions** only (`suggestion` / `a11y` / `polish`) — never auto-flip review status.  
3. If a skill finding overlaps a human pin, **merge under the pin** (enrich), don’t duplicate as a conflicting must-fix.  
4. Second opinion is scoped to **this screenshot** (+ optional URL), not the whole product.

Suggested payload shape:

```json
{
  "source": "second_opinion",
  "skill": "design-review",
  "findings": [
    {
      "severity": "suggestion",
      "area": { "x": 0.62, "y": 0.72, "w": 0.2, "h": 0.08 },
      "body": "Primary CTA contrast may fail WCAG AA on this background.",
      "related_pin": 1
    }
  ]
}
```

## UI element grounding (stronger implementations)

| Project | What it gives agents | Notes |
|---------|----------------------|--------|
| [microsoft/OmniParser](https://github.com/microsoft/OmniParser) | BBoxes + labels for UI regions from a **screenshot alone** | Best when you only have the image (ReviseMy’s default). Heavier (vision models). |
| [dirkknibbe/uipe](https://github.com/dirkknibbe/uipe) | MCP **UI scene graph** (DOM + a11y + optional OmniParser) | Ideal when a live URL is available alongside the shot. |
| [microsoft/playwright-mcp](https://github.com/microsoft/playwright-mcp) | Screenshots + accessibility tree | Lightweight grounding for local/staging URLs. |
| [Agentation](https://www.agentation.com/) | Live DOM selectors + component path | Gold standard when the page is open in-browser; ReviseMy is screenshot-first by design. |
| Pincushion (see [DEV writeup](https://dev.to/matttdamon/how-i-built-an-mcp-server-that-turns-visual-feedback-into-code-fixes-31nf)) | Pin as **work packet** (URL, selector, thread) | Pattern to copy: don’t hand agents a bare PNG — hand structured intent. |

**Recommended path for ReviseMy:**

1. **v1 (now):** human pins with normalized `x/y` + note (already shipped).  
2. **v1.1:** optional `second_opinion` job on upload — run a design-review skill / vision checklist → attach suggestions.  
3. **v1.2:** if agent also sends `page_url`, call Playwright MCP / UIPE to attach `selector` / a11y role to each pin.  
4. **v1.3:** optional OmniParser pass on the screenshot to propose element labels near each pin (“button”, “nav link”) when no DOM is available.

## Product wording

- UI: **“Second opinion”** (not “AI review” as authority).  
- Agent tool: e.g. `get_review` already returns human pins; add `second_opinion` array separately.  
- Skill for consumers: teach “apply human pins first; treat second_opinion as hints.”

## Weekend cut

Do **not** block the Cloud contest on OmniParser or ui-craft. Document the path; ship human pins + MCP first. Second opinion can be a follow-up MCP tool: `request_second_opinion(review_id)`.
