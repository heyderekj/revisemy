# Positioning and voice

Last updated: 2026-10-02

## What ReviseMy is

Visual feedback for your agent. Your agent captures the work — a screenshot, a URL, a PDF, an email — and sends you a review link. You mark what matters, approve or ask for changes, and the agent reads your marks as work and keeps going.

ReviseMy is made by Testament Made, LLC, the studio behind Koati. The two are separate products with no connection between them: no linked reviews, no webhook, nothing sent either way. They share a look and a vocabulary for marks (must fix, nice to have, question, keep this), and each draws them in its own chrome. Name Koati as a sibling, never as somewhere ReviseMy sends things.

## What ReviseMy is not

- Not a project tracker. A review has marks and passes; it does not have assignees, due dates or a backlog.
- Not an account. Nobody signs up. A try token or a one-click Connect is the whole setup, and the review link is the whole login.
- Not an AI reviewer. The second opinion offers hints; a person's marks are the only ones that count.

## Voice

Talk like a designer explaining their review habit to another designer. Plain, warm, concrete. Nothing dressed up, nothing defended.

- Short sentences. Say what happens, not what the feature is called.
- Second person for the reader. The reader's AI is "your agent".
- Verbs over nouns: mark, approve, ask for changes, verify, reopen, share.
- The em-dash is allowed for the aside that tells you the consequence. One per sentence, never two.
- No exclamation marks. No "seamless", "powerful", "leverage", "empower", "AI-powered", "supercharge", "orchestration", "engine".
- Product nouns as the app uses them: review, mark (not pin, in anything a person reads), pass, board, second opinion, guest link, try token.
- Codebase voice to match: imperative sentence-case commit subjects, prose docblocks.

## Writing copy inside the app

- Empty state: a heading of three to six words, then one line saying what will fill it. Never three lines.
- Toast: one line, past tense, naming what just happened. Where an undo exists, the toast carries it and the line stays short.
- Toast punctuation: no period when the line ends in an interpolated value, a period when it is a full sentence.
- Headings take no period when they are fragments. Supporting lines are full sentences.
- Contractions are fine and preferred.
- Errors, money and credits stay flat and exact. A light touch is allowed on empty states and success moments, never on those.
- Promise only what the setup delivers. Second opinion vision, realtime updates and paid Plus each depend on configuration; read the code before writing a sentence about them, and write the off state too.

## Where copy lives

- Connect steps for every assistant: `config/hosts.php` (`connect`), rendered by `resources/views/components/⚡connect-hub.blade.php`; the consent screen's lists are `components/connect-scope.blade.php`.
- Mark labels, statuses and board column copy: `App\Models\Annotation` (`severityLabels()`, `statusLabels()`, `boardColumnMeta()`).
- Marketing: `resources/views/components/⚡home.blade.php`, `config/use-cases.php`, `config/guides.php`, `config/alternatives.php`, `config/changelog.php`. Every public page is listed once in `App\Support\MarketingPages`.
- Review and board: `resources/views/review/partials/*`, `resources/views/components/⚡review-board.blade.php`.
- MCP tool descriptions and `next_action` summaries: `app/Mcp/Tools/*`, `App\Services\ReviewService`.

## Lines to avoid

- "Replaces your designer." A person decides; the agent does the work.
- Anything implying an account is required.
- Calling ReviseMy part of Koati, or saying it sends to Koati.
