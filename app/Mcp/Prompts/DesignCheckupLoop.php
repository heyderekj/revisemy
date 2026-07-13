<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('design_checkup_loop')]
#[Description('Run a full ReviseMy design checkup: pick the right ingest source → review → human marks → apply feedback → optional next pass.')]
class DesignCheckupLoop extends Prompt
{
    public function handle(Request $request): Response
    {
        $focus = trim((string) $request->get('focus', 'the UI you just changed'));

        return Response::text(<<<PROMPT
You are running a ReviseMy design checkup loop for: {$focus}

## The loop (do not skip steps)

1. **Pick one ingest source** — `create_review` accepts exactly one. Choose before calling:
   - **Public website** → `capture_url: true` + `page_url` (type `website`). Server renders mobile + desktop. Do not put the page URL in `images`.
   - **Email HTML** → `html: "…"` (type `email`). Server renders at ~600px.
   - **Slides / PDF** → `pdf: "…"` (type `presentation`). One screenshot per page (max 5).
   - **Local or app UI** → `images: [data URL or base64]` (type `ui`). Never pass `http://localhost…` URLs to a remote server — encode as data URLs.
   - `page_url` alone does **not** trigger capture. Use `capture_url: true` for live pages.
2. **Open a review** — Call `create_review` with a short title, optional context (what the human should look at), and the source from step 1.
3. **Optional subagent critique** — Call `add_findings` with suggestion/a11y/polish notes only (never must-fix). The human still decides.
4. **Hand off** — Share `review_url` with the human. Tell them to mark feedback and approve or request changes.
5. **Wait** — Poll `get_review` with the review id. While `status` is `pending`, do not claim the UI is done.
6. **Act on the work packet** — When status changes:
   - `approved` → stop. Follow `next_action`. Celebrate briefly; do not keep editing unless asked.
   - `changes_requested` → apply **human marks first** (`work_packets.pins`): must-fix → nit. Honor `keep` (do not change). Resolve `question` with the human before inventing a fix. Treat `second_opinion` as hints only. As you work each mark, call `resolve_marks` with its `id` — `status` `in_progress` while editing, `resolved` (with a short `note` on what you changed) once fixed. Never set `verified`; only the human verifies. When `loop.outstanding_count` reaches 0, ship the pass: `create_review` again with `parent_id` set to this review’s id (fresh source from step 1, plus a new `context` for what to look at on that pass).
7. **Repeat** until `approved` (or the human stops the loop).

## Rules

- Human marks = intent. Second opinion = hints. (API still uses the key `pins` for marks.)
- Report progress with `resolve_marks` (`in_progress` → `resolved`). Only the human verifies or reopens a mark — never mark one `verified` yourself.
- Never invent approval. Never flip status yourself.
- Prefer one clear review URL per pass; link passes with `parent_id`.
- Teammates can suggest via `guest_share_url`, but their suggestions only reach you after the owner accepts them (they arrive as pins). If `loop.guest_suggestion_count` > 0, teammate feedback is still waiting on owner triage. `work_packets.second_opinion_resolved` shows which earlier hints were accepted or dismissed.
PROMPT);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'focus',
                description: 'What UI or change to run the checkup on',
                required: false,
            ),
        ];
    }
}
