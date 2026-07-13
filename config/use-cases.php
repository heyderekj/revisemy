<?php

return [

    'pages' => [

        'ui' => [
            'slug' => 'ui',
            'review_type' => 'ui',
            'label' => 'UI',
            'icon' => 'device-phone-mobile',
            'teaser' => 'App screenshots — hierarchy, spacing, and contrast with your agent.',
            'title' => 'UI design review for AI agents — ReviseMy',
            'description' => 'Human-in-the-loop UI review for AI coding agents. Upload app screenshots, mark hierarchy and spacing issues, track fixes with before/after evidence, and send structured next steps back over MCP.',
            'keywords' => [
                'UI design review',
                'app screenshot review',
                'AI agent UI feedback',
                'visual feedback',
                'human in the loop',
                'MCP design review',
                'Cursor UI review',
            ],
            'headline' => 'UI design review with your agent',
            'subheadline' => 'Capture app screens from your agent, mark what matters on the pixels, and loop until a human approves — not when the model says it looks fine.',
            'problem' => 'Agents ship UI fast but rarely wait for a human eye on hierarchy, spacing, or contrast. Feedback scattered in chat or Slack does not map back to regions on the screen, and there is no proof a fix landed.',
            'loop' => 'Your agent opens a review with `type: ui` so the checklist and vision lens focus on hierarchy, spacing, and affordances. Pick one ingest source per review — usually screenshots of the app — then mark, approve, or request changes until `next_action` says stop.',
            'inputs' => [
                'intro' => 'Review type chooses the second-opinion lens. Ingest chooses how pixels get into the review. Use exactly one source per `create_review` call — and yes, sources overlap across types when that is what you have.',
                'items' => [
                    [
                        'key' => 'images',
                        'label' => 'Screenshots',
                        'icon' => 'photo',
                        'primary' => true,
                        'body' => 'Best default for UI. Pass 1–5 HTTPS URLs, data URLs, or base64. Required for localhost and anything the capture server cannot reach — encode as data URLs, never `http://localhost…`.',
                    ],
                    [
                        'key' => 'capture_url',
                        'label' => 'Live URL capture',
                        'icon' => 'globe-alt',
                        'primary' => false,
                        'body' => 'Public staging or Storybook URL with `capture_url: true` + `page_url`. Still set `type: ui` if you want the UI lens instead of the website checklist.',
                    ],
                    [
                        'key' => 'html',
                        'label' => 'HTML render',
                        'icon' => 'code-bracket',
                        'primary' => false,
                        'body' => 'Rare for app UI, but useful for isolated component HTML. Pair with `type: ui` so hints stay hierarchy/spacing-focused rather than email CTA rules.',
                    ],
                    [
                        'key' => 'pdf',
                        'label' => 'PDF pages',
                        'icon' => 'document',
                        'primary' => false,
                        'body' => 'When the “UI” is a design export or multi-page PDF mock. Each page becomes a screenshot; keep `type: ui` for the app checklist.',
                    ],
                ],
            ],
            'features' => [
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Rectangle marks on the exact pixels',
                    'body' => 'Drag to outline a region or click for a point note. Each mark carries intent — must-fix, nice to have, question, or keep — so agents know what to change and what to leave alone.',
                ],
                [
                    'icon' => 'arrows-right-left',
                    'title' => 'Multi-pass with before/after evidence',
                    'body' => 'Request changes to open the next pass. Agents resolve marks with notes and optional after images so you can verify fixes without re-explaining the whole screen.',
                ],
                [
                    'icon' => 'light-bulb',
                    'title' => 'Second opinion hints, human marks win',
                    'body' => 'A free UI checklist runs immediately; optional vision models can add region hints. Suggestions never override your marks or flip approve / request-changes.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'No account for reviewers',
                    'body' => 'Share the secret review link. Designers and devs mark and decide without signing up — the agent keeps the MCP handoff.',
                ],
            ],
            'checklist' => [
                'Visual hierarchy: one clear primary action, not competing CTAs',
                'Text contrast on labels and buttons (WCAG AA)',
                'Spacing rhythm — uneven gaps and cramped edge clusters',
                'Mobile-tall frames: ~44×44px tap targets and thumb-zone actions',
                'Wide desktop: readable measure, content not stretched edge-to-edge',
            ],
            'prompts' => [
                'Run a design checkup on these UI screenshots.',
                'Review this screen for hierarchy and spacing — I will mark feedback on the link.',
                'Address my UI feedback and attach after shots when you resolve each mark.',
            ],
            'faq' => [
                [
                    'q' => 'How does my agent upload UI screenshots?',
                    'a' => 'Pass images to `create_review` as HTTPS URLs, data URLs, or base64. Set `type` to `ui` (the default with images). Your agent can capture from a local build, Storybook, or a staging URL.',
                ],
                [
                    'q' => 'Can I use a live URL for UI review?',
                    'a' => 'Yes. Use `capture_url: true` with `page_url` and set `type: ui` so you still get the UI checklist. Or fall back to `images` data URLs when the page is localhost-only.',
                ],
                [
                    'q' => 'Are second-opinion hints decisions?',
                    'a' => 'No. Checklist and optional vision findings are labeled suggestions. Only your marks drive `next_action` and approve / request-changes.',
                ],
                [
                    'q' => 'Do reviewers need an account?',
                    'a' => 'No. Open the secret `/r/{token}` link from your agent. Get a try token on the homepage to connect MCP.',
                ],
            ],
        ],

        'websites' => [
            'slug' => 'websites',
            'review_type' => 'website',
            'label' => 'Websites',
            'icon' => 'globe-alt',
            'teaser' => 'Live URL capture — desktop and mobile, above-the-fold and nav.',
            'title' => 'Website design review for AI agents — ReviseMy',
            'description' => 'Live website review for AI coding agents. Server captures desktop and mobile viewports, humans mark above-the-fold and nav issues, and structured work packets return over MCP.',
            'keywords' => [
                'website design review',
                'landing page review',
                'responsive website feedback',
                'AI agent website review',
                'URL screenshot review',
                'MCP design review',
            ],
            'headline' => 'Website review with desktop and mobile capture',
            'subheadline' => 'Point your agent at a live URL. ReviseMy captures the page, runs a website-specific checklist, and keeps human marks authoritative across viewports.',
            'problem' => 'Marketing pages and landing sites get rebuilt by agents without a structured pass on above-the-fold clarity, navigation, or mobile breakpoints. Stakeholders comment in docs; nothing ties feedback to the actual rendered page.',
            'loop' => 'Your agent opens a review with `type: website` so hints target above-the-fold story, nav, and viewports. Prefer server capture from a public URL; fall back to screenshots when the site is behind auth or only on localhost.',
            'inputs' => [
                'intro' => 'Review type chooses the website lens. Ingest chooses how the page gets captured. Use exactly one source per call — mix freely when capture is unavailable or you already have shots.',
                'items' => [
                    [
                        'key' => 'capture_url',
                        'label' => 'Live URL capture',
                        'icon' => 'globe-alt',
                        'primary' => true,
                        'body' => 'Best default for public sites. `capture_url: true` + `page_url` renders desktop and mobile server-side and defaults to `type: website`.',
                    ],
                    [
                        'key' => 'images',
                        'label' => 'Screenshots',
                        'icon' => 'photo',
                        'primary' => false,
                        'body' => 'Use when the site is localhost, behind a VPN, or capture is down. Pass desktop + mobile shots yourself and set `type: website` so the checklist still matches.',
                    ],
                    [
                        'key' => 'html',
                        'label' => 'HTML render',
                        'icon' => 'code-bracket',
                        'primary' => false,
                        'body' => 'Static landing HTML or a saved page snapshot. Rendered like a document; pair with `type: website` for above-the-fold and nav hints.',
                    ],
                    [
                        'key' => 'pdf',
                        'label' => 'PDF pages',
                        'icon' => 'document',
                        'primary' => false,
                        'body' => 'Design comps or multi-page site mockups exported as PDF. Each page becomes a frame; keep `type: website` for the landing-page lens.',
                    ],
                ],
            ],
            'features' => [
                [
                    'icon' => 'computer-desktop',
                    'title' => 'Server-side desktop + mobile capture',
                    'body' => 'No manual screenshot gymnastics. Pass the URL; ReviseMy renders and stores viewport captures so review and fixes stay tied to the live page.',
                ],
                [
                    'icon' => 'eye',
                    'title' => 'Above-the-fold and nav lens',
                    'body' => 'Website checklists focus on first-visit clarity, plain-language nav, and hero contrast — the questions a landing page review should ask first.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Guest share for stakeholders',
                    'body' => 'Send a private guest link when a PM or client needs eyes on the capture. Their suggestions stay suggestions until you accept them into authoritative marks.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Multi-pass until approved',
                    'body' => 'Request changes to spawn the next pass with `parent_id`. Agents read structured pins and never claim the site is done while status is pending.',
                ],
            ],
            'checklist' => [
                'Above the fold: value proposition and next step without scrolling',
                'Navigation labels in plain language; current section obvious',
                'Heading structure and hero text contrast over imagery',
                'Mobile viewport: no horizontal overflow, tap targets ~44×44px',
                'One dominant CTA per view with outcome-focused labels',
            ],
            'prompts' => [
                'Review this URL — capture desktop and mobile and share the review link.',
                'Run a design checkup on our landing page before we ship.',
                'Apply my website marks and open a new pass with updated captures.',
            ],
            'faq' => [
                [
                    'q' => 'Does ReviseMy capture both desktop and mobile?',
                    'a' => 'Yes when you use `capture_url`. If you pass `images` instead, include both viewports yourself and set `type: website`.',
                ],
                [
                    'q' => 'What if the site is behind login?',
                    'a' => 'Server capture needs a publicly reachable URL. Capture authenticated screens yourself as `images` with `type: website` — same marks and checklist, different ingest.',
                ],
                [
                    'q' => 'Can stakeholders comment without MCP?',
                    'a' => 'Yes. Use the guest share link for suggestions. Your marks remain authoritative; guest notes are triaged by the review owner.',
                ],
                [
                    'q' => 'What about SEO and performance?',
                    'a' => 'The checklist reminds reviewers that screenshots miss title tags, Open Graph, and load performance — implement against the live DOM, not the PNG alone.',
                ],
            ],
        ],

        'email' => [
            'slug' => 'email',
            'review_type' => 'email',
            'label' => 'Email',
            'icon' => 'envelope',
            'teaser' => 'HTML at inbox width — CTA, dark mode, and footer checks.',
            'title' => 'Email design review for AI agents — ReviseMy',
            'description' => 'HTML email design review for AI coding agents. Paste email HTML, review at ~600px width, mark CTA and footer issues, and loop fixes with human sign-off over MCP.',
            'keywords' => [
                'email design review',
                'HTML email review',
                'newsletter design feedback',
                'AI agent email review',
                'email CTA review',
                'MCP design review',
            ],
            'headline' => 'Email design review at inbox width',
            'subheadline' => 'Agents paste HTML; ReviseMy renders at ~600px. Mark the dominant CTA, dark-mode risks, and footer compliance — then loop until a human approves send.',
            'problem' => 'Email gets coded by agents who optimize for modern CSS, not Outlook tables. Feedback lives in forwarded threads with no region-level marks, and nobody checks dark mode or unsubscribe footer until post-send.',
            'loop' => 'Your agent opens a review with `type: email` so hints cover CTA, dark mode, images-off, and footer rules. Prefer pasting HTML for an inbox-width render; screenshots of Litmus/Email on Acid previews work too.',
            'inputs' => [
                'intro' => 'Review type chooses the email lens. Ingest chooses how the message is rendered. Use exactly one source per call — HTML is ideal, but screenshots and other sources still get the email checklist when you set the type.',
                'items' => [
                    [
                        'key' => 'html',
                        'label' => 'Email HTML',
                        'icon' => 'code-bracket',
                        'primary' => true,
                        'body' => 'Best default. Pass raw HTML; ReviseMy renders at ~600px like a mail client and defaults to `type: email`.',
                    ],
                    [
                        'key' => 'images',
                        'label' => 'Screenshots',
                        'icon' => 'photo',
                        'primary' => false,
                        'body' => 'Client previews, dark-mode screenshots, or images-off renders from a testing tool. Set `type: email` so hints stay email-specific.',
                    ],
                    [
                        'key' => 'capture_url',
                        'label' => 'Live URL capture',
                        'icon' => 'globe-alt',
                        'primary' => false,
                        'body' => 'Hosted HTML preview URL (public). Capture with `capture_url` and set `type: email` — useful when the template is served as a page.',
                    ],
                    [
                        'key' => 'pdf',
                        'label' => 'PDF pages',
                        'icon' => 'document',
                        'primary' => false,
                        'body' => 'Design comps of the email exported as PDF. Pair with `type: email` when you want CTA/footer hints on the mock, not the slide deck checklist.',
                    ],
                ],
            ],
            'features' => [
                [
                    'icon' => 'device-phone-mobile',
                    'title' => 'Rendered at ~600px',
                    'body' => 'Review what recipients see — single-column inbox width, not a full browser canvas stretched wide.',
                ],
                [
                    'icon' => 'swatch',
                    'title' => 'CTA, dark mode, and footer lens',
                    'body' => 'Email checklists stress one dominant action, dark-mode color flips, images-off fallback, and legal footer requirements — the gaps screenshots alone cannot catch.',
                ],
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Region marks on the template',
                    'body' => 'Outline the hero, CTA block, or footer directly on the render so agents know exactly which table cell or section to fix.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Human sign-off before send',
                    'body' => 'Structured `next_action` keeps agents from marking an email “done” until you approve. Multi-pass with parent reviews for template iterations.',
                ],
            ],
            'checklist' => [
                'One dominant CTA — secondary links defer visually',
                'Subject and preheader written and complementary (not in the PNG)',
                'Dark mode: logos, borders, and pure-black text when colors invert',
                'Images-off: key content and CTA survive with images blocked',
                'Footer: unsubscribe link, physical address, sender identity',
            ],
            'prompts' => [
                'Review this email HTML before we send — share the review link.',
                'Run a design checkup on the newsletter template.',
                'Fix my email marks and attach after renders when you resolve each one.',
            ],
            'faq' => [
                [
                    'q' => 'How do I submit email HTML?',
                    'a' => 'Pass the HTML string to `create_review` with `type: email` (the default when using `html`). ReviseMy renders it server-side at roughly inbox width.',
                ],
                [
                    'q' => 'Can I review screenshots from email testing tools?',
                    'a' => 'Yes. Pass them as `images` with `type: email`. Handy for Outlook or dark-mode previews the HTML renderer cannot fully reproduce.',
                ],
                [
                    'q' => 'Does ReviseMy test every email client?',
                    'a' => 'No — it renders for human review with an email-specific checklist. Implement with table-based, ~600px layouts for Outlook and other clients.',
                ],
                [
                    'q' => 'Can vision models review the email?',
                    'a' => 'Optional Claude or OpenAI vision can add region hints when configured. Hints are suggestions only; your marks stay authoritative.',
                ],
            ],
        ],

        'slides' => [
            'slug' => 'slides',
            'review_type' => 'presentation',
            'label' => 'Slides',
            'icon' => 'presentation-chart-bar',
            'teaser' => 'PDF decks — one frame per slide for density and projection.',
            'title' => 'Slide and deck review for AI agents — ReviseMy',
            'description' => 'Presentation and PDF slide review for AI coding agents. Upload a deck, one screenshot per page, mark density and readability issues, and loop polish passes until approved.',
            'keywords' => [
                'presentation review',
                'slide deck review',
                'PDF slide review',
                'pitch deck feedback',
                'AI agent presentation review',
                'MCP design review',
            ],
            'headline' => 'Slide and deck review, one page at a time',
            'subheadline' => 'Upload a PDF deck. ReviseMy captures each slide, applies presentation-specific checks, and keeps human marks authoritative through polish passes.',
            'problem' => 'Decks built or redesigned by agents often cram too much text per slide or drift typographically slide to slide. Reviewers leave bullet comments in the doc; speakers still project unreadable gray type.',
            'loop' => 'Your agent opens a review with `type: presentation` so hints cover density, consistency, and projection readability. Prefer a PDF export; screenshots of individual slides work when PDF ingest is unavailable.',
            'inputs' => [
                'intro' => 'Review type chooses the slide lens. Ingest chooses how pages become screenshots. Use exactly one source per call — PDF is the usual path, but every source can feed a presentation review.',
                'items' => [
                    [
                        'key' => 'pdf',
                        'label' => 'PDF deck',
                        'icon' => 'document',
                        'primary' => true,
                        'body' => 'Best default. Export from Keynote, Google Slides, or PowerPoint. ReviseMy captures one screenshot per page (up to five) and defaults to `type: presentation`.',
                    ],
                    [
                        'key' => 'images',
                        'label' => 'Screenshots',
                        'icon' => 'photo',
                        'primary' => false,
                        'body' => 'Per-slide exports or presenter-view shots when Imagick/PDF ingest is unavailable. Set `type: presentation` so the deck checklist applies.',
                    ],
                    [
                        'key' => 'capture_url',
                        'label' => 'Live URL capture',
                        'icon' => 'globe-alt',
                        'primary' => false,
                        'body' => 'Published slide deck or web-based presentation URL. Capture with `capture_url` and set `type: presentation` for density/projection hints.',
                    ],
                    [
                        'key' => 'html',
                        'label' => 'HTML render',
                        'icon' => 'code-bracket',
                        'primary' => false,
                        'body' => 'HTML-based slide frameworks (Reveal.js, etc.). Render the HTML and keep `type: presentation` rather than the email lens.',
                    ],
                ],
            ],
            'features' => [
                [
                    'icon' => 'photo',
                    'title' => 'One screenshot per slide',
                    'body' => 'Each page becomes a review frame so marks land on the exact slide — not a whole-document comment thread.',
                ],
                [
                    'icon' => 'queue-list',
                    'title' => 'Density and projection checks',
                    'body' => 'Presentation checklists target one idea per slide, ~6×6 text limits, and ~24pt minimum contrast for projector readability.',
                ],
                [
                    'icon' => 'swatch',
                    'title' => 'Deck consistency marks',
                    'body' => 'Flag title position drift, mixed type scales, and chart slides that show data without a point — keep marks on the offending slide.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Multi-pass deck polish',
                    'body' => 'Request changes to open the next pass with an updated PDF. Agents resolve marks with notes before you approve the deck.',
                ],
            ],
            'checklist' => [
                'One idea per slide — split paragraphs that need explaining',
                'Text density: ~6 lines / 6 words per line max',
                'Consistent title position, type scale, and color roles',
                'Projection readability: ~24pt body minimum, strong contrast',
                'Charts make one point, stated in the slide title',
            ],
            'prompts' => [
                'Review this pitch deck PDF — one screenshot per slide.',
                'Run a design checkup on my presentation before tomorrow.',
                'Apply my slide marks and upload a revised PDF for the next pass.',
            ],
            'faq' => [
                [
                    'q' => 'How many slides per review?',
                    'a' => 'PDF ingest captures up to five pages per review. For longer decks, add `images` for more slides or open another pass.',
                ],
                [
                    'q' => 'Is this for Google Slides or Keynote?',
                    'a' => 'Export to PDF and pass it to `create_review`, or pass per-slide screenshots as `images` with `type: presentation`.',
                ],
                [
                    'q' => 'What if PDF ingest is not available?',
                    'a' => 'Fall back to `images` (one shot per slide) with `type: presentation`. Same marks, board, and checklist — different ingest.',
                ],
                [
                    'q' => 'Do agents auto-fix slides?',
                    'a' => 'Agents read your marks via `get_review` and implement in the source deck. They attach notes (and optional after images) when resolving marks.',
                ],
            ],
        ],

    ],

    'audiences' => [

        'reviewers' => [
            'slug' => 'reviewers',
            'label' => 'Reviewers',
            'icon' => 'users',
            'title' => 'Design review for humans — mark, approve, no account required',
            'description' => 'Open a ReviseMy review link as a designer, PM, or teammate. Mark regions on screenshots, set must-fix or nit, approve or request changes — no MCP install and no account.',
            'keywords' => [
                'design review link',
                'stakeholder design feedback',
                'mark screenshots',
                'no account design review',
                'human in the loop design',
                'guest design feedback',
            ],
            'headline' => 'You got a review link. Mark what matters.',
            'subheadline' => 'No MCP. No ReviseMy account. Open the secret link, outline regions on the capture, set intent, and approve or request changes — your marks tell the agent what to do next.',
            'features_heading' => 'What you can do as a reviewer',
            'checklist_heading' => 'Reviewer checklist',
            'checklist_intro' => 'A short path from opening the link to signing off.',
            'problem' => 'Feedback in Slack threads and Figma comments rarely maps back to structured work for an agent. When someone shares a ReviseMy link, you should know exactly how to leave authoritative marks — and how guest suggestions differ.',
            'loop' => 'Open `/r/{token}` from your agent or teammate. Drag a rectangle or click a point, choose must-fix / nice to have / question / keep, and leave a note. Track marks on the board from open → resolved → verified. Approve when it is done, or request changes so the agent opens the next pass.',
            'features' => [
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Precise marks on the pixels',
                    'body' => 'Outline the exact region. Each mark carries intent so the agent knows what to change and what to leave alone.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Your marks are authoritative',
                    'body' => 'Second-opinion and guest notes are suggestions until you accept them. Only you approve, request changes, or verify fixes.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Guest share for clients and PMs',
                    'body' => 'Send a private guest link when another set of eyes helps. Their suggestions stay non-authoritative until the review owner accepts them.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Board through every pass',
                    'body' => 'Follow marks from open to verified. Agents can attach before/after evidence when they resolve a mark so you can sign off with proof.',
                ],
            ],
            'checklist' => [
                'Open the secret review link — no signup',
                'Mark must-fix items first, then nits; use keep when something should stay',
                'Ask questions on the mark instead of guessing in chat',
                'Verify resolved marks (or reopen) — agents never verify for you',
                'Approve when ready, or request changes for the next pass',
            ],
            'prompts' => [
                'Share the review link so I can mark hierarchy and spacing.',
                'I left must-fix marks — address those first, then open a new pass.',
                'Send a guest link to the client for suggestions only.',
            ],
            'faq' => [
                [
                    'q' => 'Do I need to install Cursor or Claude?',
                    'a' => 'No. Reviewers only need the secret /r/{token} link. MCP setup is for the person connecting an agent.',
                ],
                [
                    'q' => 'What is the difference between marks and second opinion?',
                    'a' => 'Your marks drive the agent’s next_action. Second opinion is optional AI/checklist hints — useful, never decisions.',
                ],
                [
                    'q' => 'Can a client leave feedback without editing my marks?',
                    'a' => 'Yes. Use the guest share link. Suggestions wait for the review owner to accept them into authoritative marks.',
                ],
            ],
        ],

    ],

];
