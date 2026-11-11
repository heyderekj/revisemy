<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skill packs
    |--------------------------------------------------------------------------
    |
    | /skills shows the packs by what they review (the same four review
    | types as /for) as tabs, then industry packs and the review loop. A category
    | with no packs yet shows only its "soon" line. Each pack with a path
    | gets its own page, drawn by the guide template; an entry with an href
    | links to where that skill is already explained. The skills themselves
    | live in plugin/skills/.
    |
    */

    'categories' => [
        'websites' => [
            'tab' => true,
            'label' => 'Websites',
            'line' => 'Review and fix the pages people see before they try your product.',
            'soon' => 'Packs for pricing, feature and launch pages.',
        ],
        'apps' => [
            'tab' => true,
            'label' => 'Apps',
            'line' => 'Screens inside your product, on the web or a phone.',
        ],
        'email' => [
            'tab' => true,
            'label' => 'Email',
            'line' => 'What lands in the inbox.',
            'soon' => 'Packs for launch emails and newsletters.',
        ],
        'slides' => [
            'tab' => true,
            'label' => 'Slides',
            'line' => 'Decks people present or send.',
            'soon' => 'Packs for pitch decks and talks.',
        ],
        'industries' => [
            'label' => 'Industry packs',
            'line' => 'Extra rules for one kind of product, on top of the other packs.',
            'soon' => 'More industries, each built from real feedback.',
        ],
        'review' => [
            'label' => 'The review loop',
            'line' => 'How your agent asks you to look, and works through your marks.',
            'soon' => 'More ways for your agent to check its own work before you look.',
        ],
    ],

    'packs' => [

        'product-flow' => [
            'slug' => 'product-flow',
            'path' => '/skills/product-flow',
            'category' => 'apps',
            'label' => 'Product flow review',
            'line' => 'Review onboarding, checkout or a form across the states people actually encounter.',
            'icon' => 'squares-2x2',
            'title' => 'Product flow review skill for AI agents',
            'description' => 'Review onboarding, forms and checkout in the browser. Capture named states and put actionable findings on a ReviseMy review.',
            'keywords' => ['product design review', 'onboarding review', 'checkout review', 'agent skills'],
            'headline' => 'Review the whole flow.',
            'subheadline' => 'Follow a real task and see what happens before, during, and after it. Give each state its own frame so people can mark the exact moment that needs work.',
            'problem' => 'A screenshot of a completed screen misses the moments that make a flow confusing. A form can look great until an error appears, focus disappears, or a confirmation gives no next step.',
            'loop_steps' => [
                ['text' => 'Pick one task and capture the states that change what the person sees or can do. Name them, for example Checkout, Card declined, and Order confirmed.'],
                ['text' => 'Bring in a community skill if it helps. Better Writing can review the error message, and Review Animations can inspect the transition in the browser.'],
                ['text' => 'Ask the agent to open a ReviseMy review and add its findings as suggestions. You decide what to fix.'],
                ['text' => 'On the next pass, capture the same states with the same names and viewport to compare the fix.'],
            ],
            'features_heading' => 'One skill',
            'features' => [
                ['icon' => 'eye', 'title' => 'review-product-flow', 'body' => 'Follow a task through key states, rank observed issues, and hand the person an annotated review or a short report.'],
            ],
            'checklist_heading' => 'Install it',
            'checklist' => [
                'Run npx skills add heyderekj/revisemy --skill review-product-flow, or install the ReviseMy plugin in Claude Code.',
                'Ask your agent to review the checkout on a phone, including the declined card and confirmation states.',
            ],
        ],

        'prototype-review' => [
            'slug' => 'prototype-review',
            'path' => '/skills/prototype-review',
            'category' => 'apps',
            'label' => 'Prototype review',
            'line' => 'Compare working design options and capture the reasons behind your choice.',
            'icon' => 'squares-2x2',
            'title' => 'Prototype review skill for AI agents',
            'description' => 'Try UI variants under the same conditions, collect feedback in ReviseMy, and verify the chosen direction on the next pass.',
            'keywords' => ['prototype review', 'compare UI variants', 'design feedback', 'agent skills'],
            'headline' => 'Try the options before you choose.',
            'subheadline' => 'Give reviewers the same task in each prototype. Let them try the interaction, mark what helped or got in the way, and explain which direction they’d ship.',
            'problem' => 'A filter sheet and an inline filter panel can both look clear in a screenshot. Trying the same search on a phone shows which one makes it easier to change filters and get back to the results.',
            'loop_steps' => [
                ['text' => 'Pick one decision and one scenario. For a filter redesign, ask someone to narrow a search and then clear one filter without losing the others.'],
                ['text' => 'Use the same data, viewport, and starting state for every option. Share the working prototypes and capture a named frame for each direction.'],
                ['text' => 'Ask reviewers which direction they would choose, what they would keep, and what would make them change their mind.'],
                ['text' => 'Carry the chosen direction into the next pass and reuse its capture name and viewport so reviewers can compare the change.'],
            ],
            'features_heading' => 'One skill',
            'features' => [
                ['icon' => 'eye', 'title' => 'review-prototypes', 'body' => 'Plan a fair comparison, collect feedback on named captures, and record the decision with evidence.'],
            ],
            'checklist_heading' => 'Install it',
            'checklist' => [
                'Run npx skills add heyderekj/revisemy --skill review-prototypes, or install the ReviseMy plugin in Claude Code.',
                'Ask your agent to compare two working mobile filter prototypes and set up a review where teammates can choose a direction.',
            ],
        ],

        'homepage' => [
            'slug' => 'homepage',
            'path' => '/skills/homepage',
            'category' => 'websites',
            'label' => 'Homepage skills',
            'line' => 'Review the homepage, find its strongest section, show more, cut the rest, carry the brand.',
            'icon' => 'home',
            'title' => 'Homepage skills for your agent — feedback on app and tool landing pages',
            'description' => 'Six free skills that help your agent review and fix the homepage of an app or tool. They work with or without ReviseMy.',
            'keywords' => [
                'landing page feedback',
                'homepage review',
                'agent skills',
                'Claude Code skills',
                'marketing site critique',
            ],
            'headline' => 'Skills for a clearer homepage.',
            'subheadline' => 'Most landing pages need the same few notes: too much text, the product hidden, a heading nobody understands, no brand to remember. These skills teach your agent those notes, so it can review your homepage and fix it.',
            'problem' => 'Most homepages for apps and tools go wrong the same few ways. Makers know their product so well that they describe it instead of showing it, add sections and never take any out, and end up with a page that says a lot and shows very little. Generic advice doesn\'t help much. Specific notes on your page do.',
            'loop' => 'Install the skills and your agent holds your homepage to the same rules every time. Ask it for a review and it says what\'s working and what to change. With ReviseMy connected, that feedback lands on a review you can mark up, and your agent fixes what you agree with.',
            'loop_steps' => [
                ['text' => 'Install the skills in Claude Code, Cursor, Codex or any agent that reads skills.'],
                ['text' => 'Ask your agent to review your homepage. You get what\'s working, the strongest section, up to seven changes and two or three sites to look at.'],
                [
                    'text' => 'With ReviseMy connected, your agent captures the page with',
                    'after' => [
                        ['type' => 'text', 'value' => ' '],
                        ['type' => 'command', 'value' => 'create_review'],
                        ['type' => 'text', 'value' => ' and puts its notes on the review with '],
                        ['type' => 'command', 'value' => 'add_findings'],
                        ['type' => 'text', 'value' => '.'],
                    ],
                ],
                ['text' => 'You mark what you agree with. Your agent fixes it and sends the next pass.'],
            ],
            'features_heading' => 'Six skills',
            'features' => [
                [
                    'icon' => 'clipboard-document-list',
                    'title' => 'homepage-craft',
                    'body' => 'The rules: pass the five-second test, show the product high up, one action, less homepage, one brand carried through. Plus a shelf of reference sites by problem.',
                ],
                [
                    'icon' => 'eye',
                    'title' => 'review-homepage',
                    'body' => 'A full critique of a live page: what\'s working, the strongest section, up to seven changes and two or three references. Read-only.',
                ],
                [
                    'icon' => 'sparkles',
                    'title' => 'find-strongest-section',
                    'body' => 'Finds the section that already works best, then promotes it to the hero or rebuilds the weaker sections in its image.',
                ],
                [
                    'icon' => 'photo',
                    'title' => 'show-dont-tell',
                    'body' => 'Turns text-heavy sections into visuals that carry the point: real UI, device mockups, icon grids, photography or a mockup of the outcome.',
                ],
                [
                    'icon' => 'queue-list',
                    'title' => 'cut-homepage',
                    'body' => 'What to cut, where each cut goes instead, and the order for what\'s left.',
                ],
                [
                    'icon' => 'swatch',
                    'title' => 'brand-pass',
                    'body' => 'Finds the brand element that already works and carries it through the page. Checks the logo lockup, type, palette and imagery.',
                ],
            ],
            'checklist_heading' => 'Install them',
            'checklist_intro' => 'The skills are free and live in the ReviseMy repository. They work on their own; ReviseMy adds a review you can mark up.',
            'checklist' => [
                'Claude Code: run /plugin marketplace add heyderekj/revisemy, then /plugin install revisemy@revisemy. You get the ReviseMy server and every skill.',
                'Cursor, Codex and other agents: run npx skills add heyderekj/revisemy. You get every pack, plus design-checkup for the ReviseMy loop.',
                'Then ask your agent to review your homepage, and give it the address.',
            ],
            'faq' => [
                [
                    'q' => 'Do I need ReviseMy to use the skills?',
                    'a' => 'No. Every skill works in chat on its own. With ReviseMy connected, review-homepage puts its notes on a review you can mark up, and a capture of the page costs 5 credits.',
                ],
                [
                    'q' => 'Where do the rules come from?',
                    'a' => 'From feedback on about twenty real landing pages, most of them faith apps. The same notes kept coming back, so they became the rules. The skills never name those sites.',
                ],
                [
                    'q' => 'Will my agent change my site without asking?',
                    'a' => 'review-homepage only writes feedback. The other skills plan the change first and edit your code only when you ask them to.',
                ],
                [
                    'q' => 'Is there anything for my industry?',
                    'a' => 'These six work for any app or tool. Industry packs add rules for one kind of product on top of them. The first is for church, ministry and Bible software.',
                ],
            ],
        ],

        'faith' => [
            'slug' => 'faith',
            'path' => '/skills/faith',
            'category' => 'industries',
            'label' => 'Faith tools',
            'line' => 'Wording, proof and positioning for church, ministry and Bible software.',
            'icon' => 'church',
            'title' => 'Homepage skills for church, ministry and Bible software',
            'description' => 'An industry pack for the homepage of a church, ministry or Bible app. It adds the wording, proof and positioning faith tools get wrong, on top of the homepage skills.',
            'keywords' => [
                'church app website',
                'Bible app landing page',
                'ministry software marketing',
                'prayer app homepage',
                'agent skills',
            ],
            'headline' => 'Rules for faith tools.',
            'subheadline' => 'Church, ministry and Bible software gets the usual homepage things wrong, plus a few of its own. This pack adds those to the homepage skills, so your agent knows them too.',
            'problem' => 'A faith tool\'s homepage speaks to people from many traditions, and plenty of visitors are new. Insider words go unexplained, testimonials have no faces or churches, and prayer and study apps drift into sounding like social networks.',
            'loop' => 'faith-homepage loads alongside the homepage skills whenever the site is faith-based. Ask your agent to review your homepage and it applies both. With ReviseMy connected, the notes land on a review you mark up.',
            'loop_steps' => [
                ['text' => 'Install the homepage skills and this pack together.'],
                ['text' => 'Ask your agent to review your homepage. It applies the general rules and the faith rules.'],
                ['text' => 'You mark what you agree with. Your agent fixes it and sends the next pass.'],
            ],
            'features_heading' => 'What faith-homepage adds',
            'features' => [
                [
                    'icon' => 'document',
                    'title' => 'Plain words for insider terms',
                    'body' => 'Church plant, daily office, small group: use the plain version, or explain it in a short line in the first screen.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Proof with faces and churches',
                    'body' => 'A photo, a name, a role and a church beside every quote. Logos only when people know them.',
                ],
                [
                    'icon' => 'arrows-right-left',
                    'title' => 'The best at your practice',
                    'body' => 'Say what you help people do, such as pray together or finish the Bible, and why you do it better. Not a social network, and not named after an integration.',
                ],
                [
                    'icon' => 'photo',
                    'title' => 'Imagery that feels true',
                    'body' => 'Real congregations, homes, Bibles and hands. No stock or AI church photos, and never generated scripture.',
                ],
                [
                    'icon' => 'light-bulb',
                    'title' => 'Scripture with purpose',
                    'body' => 'One verse that explains why the product exists, and scripture shown inside the product itself, not on every section.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Plain pricing',
                    'body' => 'Free, donation or per church, stated clearly and early. Churches budget carefully.',
                ],
            ],
            'checklist_heading' => 'Install it',
            'checklist_intro' => 'faith-homepage ships with the homepage skills, so one install gets both.',
            'checklist' => [
                'Claude Code: run /plugin marketplace add heyderekj/revisemy, then /plugin install revisemy@revisemy.',
                'Cursor, Codex and other agents: run npx skills add heyderekj/revisemy.',
                'Then ask your agent to review your homepage, and give it the address.',
            ],
            'faq' => [
                [
                    'q' => 'Does it work without the homepage skills?',
                    'a' => 'It\'s built to sit on top of them and doesn\'t repeat their rules. Install both; one install gets both anyway.',
                ],
                [
                    'q' => 'Which kinds of faith tools does it cover?',
                    'a' => 'Prayer apps, Bible readers and study tools, church management, worship and sermon tools, and ministry networks.',
                ],
            ],
        ],

        'checkup' => [
            'category' => 'review',
            'label' => 'Design checkup',
            'icon' => 'cursor-arrow-rays',
            'href' => '/connectors#claude-code-plugin',
            'line' => 'Your agent sends a review when it changes something visual, then fixes what you mark.',
        ],

    ],

    'review_example' => [
        'title' => 'Example: review a declined card',
        'brief' => 'A shopper entered a card on mobile and the payment failed. Can they recover without re-entering their details?',
        'request' => 'Review the mobile checkout with Better Writing. Use test payment data and capture Checkout ready, Card declined, and Order confirmed. Add only findings you can show on those states, and send me the ReviseMy link.',
        'mark' => 'The error says “Something went wrong” above the form. I can’t tell whether to retry or change cards. Put the message beside the card field and say “Your card was declined. Try another card.”',
        'verify' => 'Capture Card declined again at the same phone width. Check that the message sits beside the card field and that shoppers can change cards without re-entering their shipping address.',
    ],

    // Original authors maintain these skills. ReviseMy publishes a short
    // recommendation and links to the source for each one.
    'community' => [
        [
            'name' => 'Prototype',
            'author' => 'Emil Kowalski',
            'focus' => 'Interactive design exploration',
            'line' => 'Build distinct versions of a UI and switch between them in the browser to try each approach.',
            'source' => 'https://aiforui.dev/skills/prototype',
            'install' => 'npx skills@latest add emilkowalski/skills --skill prototype',
            'prompt' => 'Use Prototype to explore a filter sheet and an inline filter panel. Use the same results and filters in both so I can compare how they behave on a phone.',
        ],
        [
            'name' => 'Shift Nudge',
            'author' => 'Matt D. Smith',
            'focus' => 'Screen critique',
            'line' => 'Start here when a screen feels off and you need a second pair of eyes on what to fix first.',
            'source' => 'https://shiftnudge.com/skill',
            'get' => 'Get the skill from Shift Nudge',
            'setup' => 'Shift Nudge sends the file by email.',
            'prompt' => 'Use Shift Nudge to critique this mobile settings screen. Its job is to help someone change who can see their profile. Point to the biggest issue you can see, and suggest one small change I can try.',
        ],
        [
            'name' => 'Review Animations',
            'author' => 'Emil Kowalski',
            'focus' => 'Motion',
            'line' => 'Check an existing menu, sheet, or transition for timing, easing, and responsiveness. Run the interaction in a browser to see how it moves.',
            'source' => 'https://github.com/emilkowalski/skills/tree/main/skills/review-animations',
            'install' => 'npx skills add emilkowalski/skills --skill review-animations',
            'prompt' => 'Use Review Animations on this mobile filter sheet. Open it, dismiss it, and try it with reduced motion. Tell me what you reproduced, where it happens in the code, and what to adjust.',
        ],
        [
            'name' => 'Better Writing',
            'author' => 'Jakub Krehel',
            'focus' => 'Interface copy',
            'line' => 'Give buttons, empty states, and error messages clear next steps in the product’s voice.',
            'source' => 'https://github.com/jakubkrehel/skills/tree/main/skills/better-writing',
            'install' => 'npx skills add jakubkrehel/skills --skill better-writing',
            'prompt' => 'Use Better Writing to review our empty inbox and failed-send states. For each unclear message, show the current words, an exact replacement, and the next step the person should take.',
        ],
        [
            'name' => 'Better Typography',
            'author' => 'Jakub Krehel',
            'focus' => 'Typography',
            'line' => 'Check how headings wrap, how long labels truncate, and whether the type scale holds up across viewport sizes.',
            'source' => 'https://github.com/jakubkrehel/skills/tree/main/skills/better-typography',
            'install' => 'npx skills add jakubkrehel/skills --skill better-typography',
            'prompt' => 'Use Better Typography to check this pricing page on desktop and at 375 CSS pixels. Look for awkward wraps and truncated plan names. Name the affected heading or label and suggest a specific change.',
        ],
        [
            'name' => 'Impeccable',
            'author' => 'Paul Bakaus',
            'focus' => 'Design critique',
            'line' => 'Use critique for design priorities or audit for issues such as contrast, theming, and responsive layout.',
            'source' => 'https://github.com/pbakaus/impeccable',
            'install' => 'npx impeccable install',
            'prompt' => 'Use Impeccable’s critique on this pricing page. A first-time buyer needs to choose a plan. Rank the three findings that most affect that decision and show me where each one appears.',
        ],
        [
            'name' => 'Web Design Guidelines',
            'author' => 'Vercel',
            'focus' => 'Accessibility and UI checks',
            'line' => 'Review interface code for issues such as keyboard focus, form behavior, and responsive layouts, with findings tied to source lines.',
            'source' => 'https://github.com/vercel-labs/agent-skills/tree/main/skills/web-design-guidelines',
            'install' => 'npx skills add vercel-labs/agent-skills --skill web-design-guidelines',
            'prompt' => 'Use Web Design Guidelines to review the checkout form. Check keyboard focus and how errors are announced. Give me the steps to reproduce each issue and point to the relevant file and line.',
        ],
        [
            'name' => 'Stitched Full-Page Capture',
            'author' => 'Meng To',
            'focus' => 'Capture',
            'line' => 'Capture a page in settled slices when a single screenshot misses sections that reveal as you scroll.',
            'source' => 'https://github.com/MengTo/Skills/tree/main/agent-skills/codex/stitched-full-page-capture',
            'prompt' => 'Use Stitched Full-Page Capture on this page. Its customer stories appear only after scrolling. Check that the final image includes them and tell me which parts, if any, are still missing.',
        ],
    ],

];
