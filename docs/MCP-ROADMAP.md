# MCP roadmap

**Status: future. Not built.** This captures what we'd improve in the ReviseMy MCP after comparing it with Lucidly's (lucidly.so/mcp, read 2026-10-12). Nothing here exists in code yet. Re-check Lucidly's page before acting on any of it; we compared against their public page only and never used their server.

## Two different loops

Lucidly starts with a client. They pin a comment on a live site, it becomes a ticket (LUC-42), and the agent reads it, fixes the code, replies and moves the ticket to In Review. Four tools do this: list, get, reply, update status. They also describe @mentions, turning QA notes into tickets, and creating projects from a URL.

ReviseMy starts with the agent. It captures a page, email, deck or local screen, sends a review link, reads the human's marks and proves each fix. We have 14 tools, the inline review in chat (MCP Apps), before and after images, human-only verify, a second opinion and signed decision webhooks.

We shouldn't copy their shape. A ticket system is the thing positioning says we aren't (`docs/positioning.md`: no assignees, no due dates, no backlog, no account). The gaps worth closing are the ones that make our own loop stronger.

## Where we stand

| | Lucidly | ReviseMy today |
|---|---|---|
| Who starts it | Client, on a live site | Agent, on anything visual |
| Capture | Their own, of the live site | Server capture at 2×, waited until loaded; `/screenshot` fallback is 1× |
| Sources | Live page pins | URL, email, deck, localhost images |
| Review inside the chat | Not mentioned | Yes, MCP Apps |
| Before and after proof | Not mentioned | `resolve_marks` with `after_image` |
| Who verifies | Reviewer resolves | Human only, enforced |
| Agent critique | Not mentioned | `add_findings`, second opinion |
| Pipelines | Not mentioned | Decision webhooks, progress notifications |
| Agent replies to a thread | Yes | Yes, `reply_to_marks`; threads show inline read-only |
| Query past feedback | Project, status, page, device, ticket | `list_reviews`, this token only |
| AI actions labeled | Yes | Yes, an Agent tag on notes, statuses, after images, replies and findings |
| Activity log, revoke | 90-day log, one-click disconnect | Not visible |
| Setup over SSH | One-step command | Token paste |
| Published rate limits | Per plan | Not stated |

## Proposed work

Ordered by value for effort. Each item ships web and inline review together (`docs/CONNECTORS.md` § MCP ↔ web parity checklist).

### 0. Capture you can trust

Every item below leans on the shot. Proof is an after image, and the human marks what they can see, so a soft or half-loaded capture undercuts all of it. Shipped:

- **Waiting:** a load gate waits for every image, CSS background, autoplaying video and font, then a quiet network, and `/function` adds a short network-idle wait. Each shot's meta records `loaded` and `incomplete_images`.
- **Sharpness:** desktop, mobile and tablet at 2× through `/function`, scaled down only to stay under a pixel budget and Chrome's 16,000px height. Thumbnails come from the browser, so no tall image is decoded in PHP.
- **Layout:** pages lay out at the full viewport width (no scrollbar strip), and tablet reads as an iPad.
- **Display:** the board and the inline review show each shot at the size the page was drawn at.
- **A vision copy:** `/function` also shoots the top of the page at 1× or less, within 1568px on the long edge and about 1.15MP, so Anthropic reads it as sent. The second opinion sends that copy, and its regions map back onto the full shot.
- **One checklist per review:** the checklist never looks at the pixels, so it now runs once, on the first shot, and takes its viewport notes from every shot. A URL capture gets one set of hints instead of the same set on each of its three shots. Vision findings stay per shot. On a later shot, the web review and the inline review both say where the checklist is.

Still to do:

- **Production on `/function`.** It has been falling back to `/screenshot`, which is 1× for desktop and tablet. `php artisan revisemy:probe-capture` shows Browserless's reason. `REVISEMY_CAPTURE_SESSION_MAX_MS` fixes it if the cause is the session timeout.
- **Vision below the fold, and off `/function`.** The vision copy covers the top 1568 CSS px of the page only (about two screens on desktop and phone), so the second opinion says nothing about the rest. Slices would cover it. Shots from `/screenshot`, Browsershot and uploads have no copy and still go in whole, so a tall one can be turned down. Production runs no vision key today, so the second opinion is the checklist only.

### 1. Let the agent reply on a mark (shipped)

`reply_to_marks` puts the agent's reply in a mark's thread: an answer to a question, an explanation of a fix, or a question before it guesses. It works while the review waits on the person or after they ask for changes, respects comments being off, and never changes a mark's status. `get_review` already returned each pin's last 10 comments; they now carry `author_type`. The inline review shows threads read-only, and replying stays on the web.

### 2. Label what the agent did (shipped)

An Agent tag, in the sky agent tone, marks the agent's notes, the statuses it set (`status_by` on each pin), its after images, its replies and its findings. It reads the same on the review page, the board, guest links and the inline review, so a guest can tell a person from the agent at a glance. We said "Agent", not "AI", to match "your agent".

### 3. Make `list_reviews` a real query

Add filters for status, type, page host and "has outstanding marks", and a workspace-wide scope behind the signed-in connection. Return pass number and counts only, as now. The aim is a fresh chat asking "what's still open on /pricing?" and getting an answer.

### 4. Show what each assistant did

A page listing what each connected assistant read or changed, with a one-click disconnect. Keep it short-lived (they keep 90 days) and name the assistant the way the Connect page does (`AssistantCallback::identify()`). This is their strongest trust line and we have no equivalent.

### 5. Turn notes into marks

A tool that takes pasted QA notes or a call summary and drops them as marks on a review, each tied to a screenshot position the agent picks. It stays a draft until the human looks: the agent proposes, the human's marks are the only ones that count.

### 6. Easier setup off the laptop

A one-command setup for SSH sessions and containers, where browser sign-in isn't available. Today that means pasting a try token. Also publish rate limits per plan on the connect page and in `docs/CONNECTORS.md`.

### 7. A page's history across chats

Lucidly has ticket numbers and projects. We shouldn't add either, but a review chain is already a page's history. Make it easy to ask for "the last pass on this URL" so a new chat picks up where the old one stopped. This is a lookup on existing parent and child reviews, not a new object.

## Decided against

- **Tickets, assignees, a backlog.** Positioning rules them out.
- **Teammate @mentions as a feature.** It implies accounts. A guest link covers the same need.
- **A new project object.** See item 7.
- **Letting the agent verify.** Only the human verifies or reopens a mark.

## Where to lead instead

What they don't claim is proof. A fix with an after image the human can check against the mark, a human-only sign-off, and the same loop for emails and decks. Keep leading with verify and before and after in copy (`docs/positioning.md`, `config/alternatives.php`), and add Lucidly to the alternatives page only once this doc's top items have shipped.

## Open questions

- ~~Should replies from the agent be visible to guests, or only to the owner?~~ Guests see them, labelled, like every other part of the thread.
- Does a workspace-wide `list_reviews` need its own scope on the OAuth consent screen?
- Is the activity log per workspace or per connection, and who can see it?
