# Browser extension (planned)

**Not built yet.** Written 2026-09-25. Started from a post by Jesse Thanley showing a browser tool that selects a DOM element, edits its text, takes comments, and packages it all as a prompt for an agent.

**What it is.** ReviseMy's review canvas works on a picture of the page. The extension brings marking to the live page itself. Click any element on any site, including localhost, staging, or a branch preview. Leave a mark, and optionally change its words in place. The extension packages it for the agent in the same work-packet shape `get_review` already returns.

**Product terms are unchanged.** Marks, not pins. Human marks are authoritative. Suggestions from the second opinion never override them (`docs/SECOND-OPINION.md`).

## Why this and not the extensions that exist

Picking an element for an agent is already common. There are free, open-source tools for it:

- **Agent Markup** edits text in place and copies one prompt.
- **React Grab** copies an element's context for Cursor or Claude Code.
- **DOM Review** leaves comments that an agent reads through the Chrome DevTools MCP.

All three stop at the clipboard. ReviseMy's reason to exist is what happens after the prompt:

1. The agent resolves each mark with a note and an `after_image`.
2. The human verifies or reopens it.
3. Passes stack in the ledger until the human approves.

The extension is a better front door to that loop. It is not a new loop.

## The gesture

1. Turn it on for the tab, from the toolbar button or a shortcut.
2. Hover to outline elements, then click one to select it. The same selection works on any site, because it runs on the page's own DOM.
3. The small bar beside the element offers two actions:
   - **Mark.** Write the note and pick a severity from the composer's list: must-fix, nit, question, or keep.
   - **Edit text.** Double-click, or press Edit, and change the words right there. The edit only changes what's on your screen. It becomes a mark whose `suggested_copy` holds the new words and whose note holds the old ones, which is exactly the field agents already apply without inventing wording.
4. Keep going across the page, or across pages. The marks collect in a tray.
5. Send them.

## What it captures per mark

It captures the same shape Koati's snippet captures, because both use one picker package (below):

- the page URL and the viewport size,
- a stable selector: an id, then Webflow's `data-w-id`, then a `data-*` source hint or a source-map file when the page has one, then a DOM path,
- the element's text, its role, and its box,
- a viewport screenshot from `chrome.tabs.captureVisibleTab`, with the mark's area normalised the way `NormalizedArea` stores it today,
- the note, the severity, and `suggested_copy` when the words were edited.

## Where it sends

Three destinations, from least setup to most:

- **Copy as prompt.** No account and no network. It copies a plain-text work packet to the clipboard, in the same field order as `work_packets.pins`, ready to paste into any agent. This is the free floor that matches the existing tools.
- **Send to ReviseMy.** This uses the try token the person pastes into the extension once.
  - It calls `POST /api/reviews` with the screenshots and `type: website`, and places the human's marks on them.
  - It returns the review URL, and the agent picks it up with `get_review` as usual.
  - Review type, second opinion, passes and the board all work unchanged.
- **Send to a team's Koati.** Nothing new is needed.
  - Create the review with the team's `webhook_url` pointed at Koati's `POST /inbound/revisemy`.
  - When the human requests changes, each mark lands in Koati's inbox as a line to sort (Koati's `docs/inbound.md` § ReviseMy).
  - This is the path for work that needs someone to agree to it before any agent touches it.

**The split with Koati is deliberate.**

- **ReviseMy goes straight to the maker's own agent.** That fits somebody fixing their own work.
- **Koati sends everything through an inbox that a person sorts.** A colleague's or client's click must never reach an agent unreviewed.
- **Koati's own snippet covers people who install nothing.** That is its Website view. This extension is for the person who makes the work.

## New in the code

- `POST /api/reviews/{publicId}/marks`, behind `auth:sanctum` like the rest of `routes/api.php`.
  - It lets a human add marks over HTTP, which today only the MCP Apps `add_mark` tool can do.
  - Marks made this way are human marks, and therefore authoritative.
  - It follows the same validation as `AddMarkTool`, including `suggested_copy` sanitised through `FeedbackText`.
- A `selector` and `page_url` on a mark, stored beside its area, so the agent knows which element in the source to change, not only which pixels.
- The extension itself:
  - It is a Manifest V3 extension.
  - It uses the `activeTab` permission and no host permissions, so it can only read a tab while the user has turned it on there.
  - It does no background reading of any site.

## The shared picker

The element picker is one package, shared with Koati.

- **What it does.** It handles hover outlines, the selector rules, the in-place text edit, and the capture shape above.
- **Where it comes from.** It is published under the MIT license with Koati's Views SDK. The design is in Koati's `docs/website-view.md` § One picker.
- **Why it's shared.** ReviseMy and Koati would otherwise drift into two pickers that capture different things.
- **What stays separate.** The package is only the picker. The tray, the destinations and the ReviseMy chrome stay in this repository. Koati never absorbs ReviseMy chrome.

## Still open

- **Design tokens.** Koati's Website view lets a person turn a CSS custom property as a proposed change. It isn't settled whether the extension offers the same thing, a colour or spacing slider on the live page that becomes a mark, or stays with text and marks. Start with text.
- **Firefox and Safari.** Chrome first. The picker package has no Chrome-only code, so other browsers are mostly a packaging job.
- **Store listing.** Its copy follows ReviseMy's own voice. It never describes the extension as editing a site, because the only thing it changes is what's on your screen.
