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
            'teaser' => 'Mark and approve on the link — no account.',
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

        'designers' => [
            'slug' => 'designers',
            'label' => 'Designers',
            'icon' => 'paint-brush',
            'teaser' => 'Hierarchy and craft on the pixels.',
            'title' => 'Design review for designers — mark hierarchy, approve agent UI',
            'description' => 'Review agent-built UI as a designer. Mark hierarchy, spacing, and contrast on the pixels, keep second-opinion hints in their place, and approve only when the craft holds up.',
            'keywords' => [
                'designer design review',
                'UI hierarchy feedback',
                'agent-built UI review',
                'visual design critique',
                'human in the loop design',
                'MCP design review',
            ],
            'headline' => 'Your eye is the brief — not the model’s claim that it looks fine.',
            'subheadline' => 'Open the review link, mark hierarchy and spacing on the capture, and approve or request changes. Agents implement; you decide when the craft is done.',
            'features_heading' => 'Built for design craft',
            'checklist_heading' => 'Designer checklist',
            'checklist_intro' => 'From opening the link to signing off on the pass.',
            'problem' => 'Agents ship screens quickly, then declare the UI done. Chat feedback does not stick to regions, and second-opinion hints can feel louder than your craft judgment unless marks stay authoritative.',
            'loop' => 'Open `/r/{token}` (or ask your teammate’s agent for a link). Outline must-fix hierarchy and spacing issues first, use keep where something should stay, verify resolved marks with before/after evidence, then approve — or request the next pass.',
            'features' => [
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Marks on the exact pixels',
                    'body' => 'Drag a rectangle or click a point. Intent (must-fix, nice to have, question, keep) tells the agent what to change and what not to touch.',
                ],
                [
                    'icon' => 'swatch',
                    'title' => 'Craft over autocomplete',
                    'body' => 'Checklist and vision hints can surface ideas, but they never override your marks or flip approve / request-changes.',
                ],
                [
                    'icon' => 'eye',
                    'title' => 'Verify with proof',
                    'body' => 'Agents can attach after shots when they resolve a mark. You verify — they never verify for you.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Multi-pass until it feels right',
                    'body' => 'Request changes to open the next capture. Follow the board from open → resolved → verified across passes.',
                ],
            ],
            'checklist' => [
                'Open the review link — no MCP required for reviewing',
                'Mark hierarchy and primary action first, then spacing and contrast nits',
                'Use keep when the agent should leave something alone',
                'Verify resolved marks (or reopen) before approving',
                'Approve when craft holds, or request changes for pass two',
            ],
            'prompts' => [
                'Run a design checkup on these screens — I will mark hierarchy and spacing.',
                'Address my must-fix marks first, then open a new pass with after shots.',
                'Share the review link so I can approve or request changes.',
            ],
            'faq' => [
                [
                    'q' => 'Do I need Cursor or Claude installed?',
                    'a' => 'Not to review. Open the secret `/r/{token}` link. MCP is for whoever connects the agent.',
                ],
                [
                    'q' => 'Will second opinion override my marks?',
                    'a' => 'No. Checklist and vision findings are suggestions. Your marks drive next_action.',
                ],
                [
                    'q' => 'Can I review websites and email too?',
                    'a' => 'Yes. Same loop — pick the review type (UI, websites, email, slides) so the checklist matches the artifact.',
                ],
            ],
        ],

        'product' => [
            'slug' => 'product',
            'label' => 'Product',
            'icon' => 'clipboard-document-list',
            'teaser' => 'Prioritize what ships without living in agent chat.',
            'title' => 'Design review for product — marks, priorities, guest links',
            'description' => 'Leave structured product feedback on agent-built UI without installing MCP. Mark must-fix vs nice-to-have, share guest links with stakeholders, and keep decisions with the review owner.',
            'keywords' => [
                'product manager design review',
                'stakeholder UI feedback',
                'guest design review link',
                'must-fix vs nice to have',
                'product design feedback',
                'no account design review',
            ],
            'headline' => 'Prioritize what ships — without living in the agent chat.',
            'subheadline' => 'Open the review link, mark must-fix vs nits on the capture, and invite stakeholders on a guest link. No MCP install for product or clients.',
            'features_heading' => 'Built for product decisions',
            'checklist_heading' => 'Product checklist',
            'checklist_intro' => 'Turn a vague “doesn’t feel right” into work the agent can follow.',
            'problem' => 'Product feedback in Slack and docs rarely maps to pixels. Stakeholders pile on in threads while the agent waits for a clear brief — and guest opinions can blur into decisions if roles are fuzzy.',
            'loop' => 'Open `/r/{token}` from engineering or design. Mark must-fix items first, questions where you need clarity, and keep where scope should not change. Share a guest link for PM/client eyes; accept their suggestions only when you want them authoritative. Approve or request the next pass.',
            'features' => [
                [
                    'icon' => 'queue-list',
                    'title' => 'Intent on every mark',
                    'body' => 'Must-fix, nice to have, question, or keep — so the agent knows priority without another meeting.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Guest links for stakeholders',
                    'body' => 'Clients and partners leave suggestions. They stay non-authoritative until the review owner accepts them.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Owner decisions stay clear',
                    'body' => 'Only the review owner approves, requests changes, or verifies. Product can own the link without running MCP.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Pass-based scope control',
                    'body' => 'Request changes when the bar is not met. Track open → resolved → verified so nothing gets lost between releases.',
                ],
            ],
            'checklist' => [
                'Open the secret review link — no signup',
                'Mark must-fix outcomes first; park nits as nice to have',
                'Use question when requirements are unclear',
                'Send a guest link when stakeholders need eyes (suggestions only)',
                'Approve when the pass meets the bar, or request changes',
            ],
            'prompts' => [
                'Share the review link so I can mark must-fix vs nice-to-have.',
                'Send a guest link to the client for suggestions only.',
                'I left product marks — address must-fix first, then open a new pass.',
            ],
            'faq' => [
                [
                    'q' => 'Do PMs need MCP?',
                    'a' => 'No. Reviewers and product owners only need the review link. Engineering connects the agent.',
                ],
                [
                    'q' => 'What is a guest link for?',
                    'a' => 'Stakeholders who should suggest without owning approve / request-changes. You accept suggestions into marks when ready.',
                ],
                [
                    'q' => 'How do I keep scope from ballooning?',
                    'a' => 'Use must-fix sparingly, mark keep on intentional choices, and request a new pass instead of endless chat edits.',
                ],
            ],
        ],

        'engineers' => [
            'slug' => 'engineers',
            'label' => 'Engineers',
            'icon' => 'command-line',
            'teaser' => 'Ship the fix, prove it, wait for a human eye.',
            'title' => 'Design review for engineers — MCP loop, resolve marks, before/after',
            'description' => 'Connect ReviseMy over MCP, open reviews from screenshots or URLs, resolve human marks with notes and after shots, and follow next_action until approval.',
            'keywords' => [
                'engineer design review MCP',
                'resolve_marks before after',
                'AI agent UI feedback loop',
                'Cursor design review',
                'create_review get_review',
                'human in the loop engineering',
            ],
            'headline' => 'Ship the fix, prove it, wait for a human eye.',
            'subheadline' => 'Wire MCP once, open a review from your agent, implement marks, resolve with notes and optional after images, and poll get_review until next_action says stop.',
            'features_heading' => 'Built for the agent workflow',
            'checklist_heading' => 'Engineer checklist',
            'checklist_intro' => 'From try token to approved pass.',
            'problem' => 'Agents change UI in chat without a durable review artifact. Human feedback gets buried, and “fixed” claims lack before/after proof tied to specific marks.',
            'loop' => 'Get a try token on the homepage, connect your host (Cursor, Claude, Copilot, …), call create_review with the right source, share review_url (or open inline on MCP Apps hosts). Implement pins, resolve_marks with evidence, poll get_review, and follow next_action — wait, apply marks, open another pass, or stop.',
            'features' => [
                [
                    'icon' => 'puzzle-piece',
                    'title' => 'One MCP endpoint',
                    'body' => 'Same Bearer try token and `/mcp/revisemy` URL across hosts. Ask agent or Do it myself on the homepage for host-specific setup.',
                ],
                [
                    'icon' => 'arrows-right-left',
                    'title' => 'resolve_marks with evidence',
                    'body' => 'Close each human pin with a note and optional after image so verification is concrete.',
                ],
                [
                    'icon' => 'queue-list',
                    'title' => 'next_action is the contract',
                    'body' => 'get_review returns work packets and one clear next step — no guessing whether to wait or open pass two.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'Link or inline review',
                    'body' => 'CLI hosts share review_url; MCP Apps hosts can render the review inline. Same marks either way.',
                ],
            ],
            'checklist' => [
                'Mint a try token and connect MCP for your host',
                'create_review with one source (images, capture_url, html, or pdf)',
                'Share review_url (or open inline) and wait while humans mark',
                'Fix pins; resolve_marks with notes / after shots',
                'Poll get_review and follow next_action until approved',
            ],
            'prompts' => [
                'Run a ReviseMy design checkup on the work I just changed.',
                'Address my feedback — resolve each mark and attach after shots.',
                'Open a new pass with fresh captures after I request changes.',
            ],
            'faq' => [
                [
                    'q' => 'Where do I get the MCP config?',
                    'a' => 'Homepage Try with your agent — Ask agent pastes a filled prompt, or Do it myself copies URL, Bearer, and host JSON.',
                ],
                [
                    'q' => 'Can I review localhost UI?',
                    'a' => 'Yes. Prefer screenshots as data URLs. Public staging can use capture_url + page_url.',
                ],
                [
                    'q' => 'Who verifies marks?',
                    'a' => 'Humans only. Agents resolve; reviewers verify or reopen.',
                ],
            ],
        ],

        'founders' => [
            'slug' => 'founders',
            'label' => 'Founders',
            'icon' => 'rocket-launch',
            'teaser' => 'Agent speed with a human checkpoint.',
            'title' => 'Design review for founders — solo try-token path with a human checkpoint',
            'description' => 'Ship agent-built UI with a free try token and a human approve step. No ReviseMy account — connect MCP, open a review, mark what matters, and loop until it feels right.',
            'keywords' => [
                'indie hacker design review',
                'founder UI feedback',
                'try token MCP',
                'solo AI agent design loop',
                'human checkpoint for AI UI',
                'no account design review',
            ],
            'headline' => 'Move fast with agents — still get a human checkpoint.',
            'subheadline' => 'Grab a free try token, paste MCP into the agent you already use, run a checkup, and mark the link yourself (or send it to a friend). Approve when it is good enough to ship.',
            'features_heading' => 'Built for shipping solo',
            'checklist_heading' => 'Founder checklist',
            'checklist_intro' => 'Weekend-speed setup, durable feedback.',
            'problem' => 'Solo builders let agents ship UI end-to-end and only notice craft issues after users do. Chat history is not a review board, and “LGTM” from the model is not a human pass.',
            'loop' => 'Try with your agent on the homepage → connect one host → create_review → open review_url → mark must-fix items (even if you are the only reviewer) → agent fixes and resolves → you verify and approve. Repeat until next_action says stop.',
            'features' => [
                [
                    'icon' => 'link',
                    'title' => 'Free try token, no account',
                    'body' => 'Mint a Bearer token on the homepage. Same endpoint for Cursor, Claude, Copilot, ChatGPT, or Grok.',
                ],
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'You can be the reviewer',
                    'body' => 'Wear both hats: run the agent, then open the link and mark what still feels off.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Or borrow another set of eyes',
                    'body' => 'Share the review or guest link when a designer friend can spare five minutes.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Short loops, clear stop',
                    'body' => 'Request changes for another pass. Approve when it is shippable — structured next_action either way.',
                ],
            ],
            'checklist' => [
                'Generate a try token on the homepage',
                'Connect one agent host (Ask agent is fastest)',
                'Run a design checkup on what you just built',
                'Mark must-fix issues on the review link',
                'Approve when ready — or request changes and loop',
            ],
            'prompts' => [
                'Run a design checkup on this screen — I will mark what to fix.',
                'Address my marks and attach after shots when you resolve them.',
                'Share a guest link so a friend can suggest without owning the review.',
            ],
            'faq' => [
                [
                    'q' => 'Do I need a ReviseMy account?',
                    'a' => 'No. Try tokens are enough to connect MCP and open reviews.',
                ],
                [
                    'q' => 'What if I am the only human?',
                    'a' => 'That works. Mark your own link — the point is a durable checkpoint, not a committee.',
                ],
                [
                    'q' => 'How long do try tokens last?',
                    'a' => 'Seven days. Generate a new token from the credentials card if it expires or was shared by mistake.',
                ],
            ],
        ],

        'agencies' => [
            'slug' => 'agencies',
            'label' => 'Agencies',
            'icon' => 'building-office-2',
            'teaser' => 'Run the agent; clients mark on the link.',
            'title' => 'Design review for agencies — client guest links, agent builds, human sign-off',
            'description' => 'Run agents inside the studio while clients mark on a guest or review link. Keep suggestions non-authoritative until you accept them, and ship multi-pass approvals with before/after proof.',
            'keywords' => [
                'agency design review',
                'client guest feedback link',
                'freelance UI review AI',
                'client approval design loop',
                'multi-stakeholder design review',
                'agent builds client marks',
            ],
            'headline' => 'You run the agent. Clients mark on the link.',
            'subheadline' => 'Keep MCP inside the studio. Send clients a guest link for suggestions — or an owner review link when they should approve. Multi-pass with before/after so sign-off is evidence-based.',
            'features_heading' => 'Built for studio + client workflows',
            'checklist_heading' => 'Agency checklist',
            'checklist_intro' => 'Clear roles from internal build to client approval.',
            'problem' => 'Agencies that use coding agents still collect client feedback in email and Figma threads. Clients should not need MCP, and their comments should not silently become the brief until the studio accepts them.',
            'loop' => 'Studio connects MCP and opens create_review. Internally, designers/engineers leave authoritative marks. For clients, share a guest link (suggestions only) or the owner link when they should approve. Accept guest notes into marks when appropriate, request changes for the next pass, verify with after shots, then approve.',
            'features' => [
                [
                    'icon' => 'building-office-2',
                    'title' => 'MCP stays in the studio',
                    'body' => 'Agents and try tokens live with the team. Clients never install Cursor or paste Bearer tokens.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Guest links for clients',
                    'body' => 'Suggestions stay non-authoritative until the review owner accepts them into marks.',
                ],
                [
                    'icon' => 'arrows-right-left',
                    'title' => 'Before/after for approvals',
                    'body' => 'Resolve marks with after images so client sign-off is about proof, not vibes.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Who decides stays explicit',
                    'body' => 'Studio owns the brief unless you hand them the owner link. Guest eyes never flip approve by accident.',
                ],
            ],
            'checklist' => [
                'Connect MCP on the studio side (try token or deploy)',
                'Open a review; leave internal must-fix marks first',
                'Send clients a guest link for suggestions — or owner link to approve',
                'Accept guest notes into marks only when you agree',
                'Request changes / verify after shots until client sign-off',
            ],
            'prompts' => [
                'Open a review for this client deliverable and share the link with me.',
                'Send a guest link to the client for suggestions only.',
                'Address studio must-fix marks, attach after shots, then we will send pass two.',
            ],
            'faq' => [
                [
                    'q' => 'Should clients get MCP access?',
                    'a' => 'Usually no. Guest or review links are enough. Keep try tokens inside the studio.',
                ],
                [
                    'q' => 'Guest link vs owner review link?',
                    'a' => 'Guest = suggestions only. Owner link can mark authoritatively and approve / request changes — use when the client is the decision-maker.',
                ],
                [
                    'q' => 'How do we show what changed?',
                    'a' => 'Have the agent resolve_marks with after images; reviewers verify on the board before the next client send.',
                ],
            ],
        ],

    ],

];

