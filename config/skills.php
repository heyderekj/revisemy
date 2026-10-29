<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skill packs
    |--------------------------------------------------------------------------
    |
    | /skills lists the packs by category; each pack with a path gets its own
    | page, drawn by the guide template. An entry with an href instead links
    | to where that skill is already explained. The skills themselves live in
    | plugin/skills/.
    |
    */

    'categories' => [
        'marketing' => [
            'label' => 'Marketing sites',
            'line' => 'Review and fix the pages people see before they try your product.',
            'soon' => 'Packs for pricing, feature and launch pages.',
        ],
        'industries' => [
            'label' => 'Industry packs',
            'line' => 'Extra rules for one kind of product, on top of the homepage skills.',
            'soon' => 'More industries, each built from real feedback.',
        ],
        'review' => [
            'label' => 'The review loop',
            'line' => 'How your agent asks you to look, and works through your marks.',
            'soon' => 'More ways for your agent to check its own work before you look.',
        ],
    ],

    'packs' => [

        'homepage' => [
            'slug' => 'homepage',
            'path' => '/skills/homepage',
            'category' => 'marketing',
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

];
