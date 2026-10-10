<?php

return [

    'pages' => [

        'connectors' => [
            'slug' => 'connectors',
            'path' => '/connectors',
            'label' => 'Connectors',
            'icon' => 'puzzle-piece',
            'title' => 'Connect ReviseMy to Claude, ChatGPT, Cursor, VS Code, Grok, Muse or Codex',
            'description' => 'Add ReviseMy to the assistant you already use. Claude, ChatGPT and Grok connect with one address, Cursor and VS Code in one click, Muse and Codex with a try token.',
            'keywords' => [
                'MCP connectors',
                'Claude custom connector',
                'ChatGPT connector',
                'Cursor MCP',
                'VS Code MCP',
                'Grok MCP',
                'Muse connector',
                'design review MCP',
            ],
            'headline' => 'Connect the assistant you already use',
            'subheadline' => 'One address for every assistant. Most connect by pasting it and clicking Connect — no account, no token to copy.',
            'problem' => 'Every assistant adds a connector a little differently, and setup docs drift from what the app actually asks for.',
            'loop' => 'Pick your assistant below and follow its two or three steps. The page shows when it’s connected, then you ask for a design checkup.',
            'loop_steps' => [
                ['text' => 'Pick your assistant and follow its steps: paste and Connect, one click, or a try token.'],
                ['text' => 'This page shows the moment your assistant makes its first call.'],
                ['command' => 'create_review', 'text' => 'starts a checkup. You mark it, approve or ask for changes, and your agent keeps going.'],
            ],
            // Rendered by <livewire:connect-hub>, from config/hosts.php `connect`.
            'hosts' => true,
            // Folded in from /mcp-apps and /webhooks (both redirect here).
            'sections' => [
                [
                    'id' => 'claude-code-plugin',
                    'heading' => 'A plugin for Claude Code',
                    'body' => 'The ReviseMy plugin adds the server and a design-checkup skill in one step, so Claude Code knows when to ask you for a review.',
                    'items' => [
                        'In Claude Code, run /plugin marketplace add heyderekj/revisemy, then /plugin install revisemy@revisemy.',
                        'The first checkup signs you in, the same as Connect.',
                    ],
                ],
                [
                    'id' => 'mcp-apps',
                    'heading' => 'Reviews right in the chat',
                    'body' => 'In Claude and VS Code, the review opens inside the conversation, so you mark and decide without leaving it. Everywhere else your agent shares the review link. Same loop either way; comments, guest links and the full board live on the link.',
                    'items' => [
                        'Approving, asking for changes and verifying are yours: those controls exist only for you in the inline view.',
                        'Your agent reads what to do next from get_review, wherever you decided.',
                    ],
                ],
                [
                    'id' => 'webhooks',
                    'heading' => 'Webhooks for CI',
                    'body' => 'Pass an HTTPS webhook_url to create_review and ReviseMy posts a signed review.decided event when you approve or ask for changes, so a pipeline can wait on you without polling.',
                    'items' => [
                        'Verify X-ReviseMy-Signature: an HMAC-SHA256 of the raw body, keyed with the review’s owner token.',
                        'Branch on review.status, and read review.next_action for what comes next.',
                        'Later passes inherit the webhook. Addresses inside private networks are refused, redirects aren’t followed, and five failed deliveries in a row pause it.',
                    ],
                ],
            ],
            'faq' => [
                [
                    'q' => 'Do I need a ReviseMy account?',
                    'a' => 'No. Connect makes a try workspace that’s yours, and the people you ask for a review only need its link.',
                ],
                [
                    'q' => 'What do I say to start a review?',
                    'a' => 'Ask it to check, proof, mark up or get feedback on something visual. Assistants know to leave code review alone. Add your own words on Your reviews, or pick check_page from your assistant’s prompts.',
                ],
                [
                    'q' => 'My assistant isn’t listed.',
                    'a' => 'Any MCP client can use the address. It signs in the same way, or takes a try token as a Bearer header.',
                ],
                [
                    'q' => 'Can my agent approve for me?',
                    'a' => 'No. It creates reviews and reports its fixes. Approving, asking for changes and verifying stay with you.',
                ],
            ],
        ],

        'second-opinion' => [
            'slug' => 'second-opinion',
            'path' => '/second-opinion',
            'label' => 'Second opinion',
            'icon' => 'light-bulb',
            'mark_icon' => 's',
            'title' => 'Second opinion design hints — checklist and vision that never override your marks',
            'description' => 'A free design checklist, plus optional Claude or OpenAI vision, on every capture. Hints only — your marks decide, and nothing approves itself.',
            'keywords' => [
                'AI design critique',
                'second opinion',
                'design checklist',
                'vision design review',
                'human in the loop',
                'AI agent design hints',
            ],
            'headline' => 'Second opinion hints. Your marks decide.',
            'subheadline' => 'Every upload gets a free, type-aware checklist. Optional vision models can mark regions on the capture. Suggestions never override human marks or flip the review decision.',
            'features_heading' => 'How second opinion works',
            'problem' => 'Paste-into-chat critique often sounds decisive — and agents treat it that way. You need optional design hints that stay labeled as suggestions while humans remain the authority on approve / request-changes.',
            'loop' => 'On create_review, ReviseMy runs the free checklist immediately. If an Anthropic or OpenAI key is set on the server, vision can add dashed region hints after the response. Agents may also push findings via add_findings. You mark what matters; get_review returns work_packets with pins first and second_opinion as hints.',
            'loop_steps' => [
                [
                    'command' => 'create_review',
                    'text' => 'runs the free checklist immediately, led by your DESIGN.md when there is one.',
                ],
                [
                    'text' => 'With an Anthropic or OpenAI key, vision can add dashed region hints.',
                ],
                [
                    'command' => 'add_findings',
                    'text' => 'lets agents push suggestions before you open the link.',
                ],
                [
                    'command' => 'get_review',
                    'text' => 'returns your marks first;',
                    'after' => [
                        ['type' => 'text', 'value' => ' '],
                        ['type' => 'command', 'value' => 'second_opinion'],
                        ['type' => 'text', 'value' => ' stays hints.'],
                    ],
                ],
            ],
            'features' => [
                [
                    'icon' => 'check',
                    'title' => 'Free checklist on every upload',
                    'body' => 'Type-aware heuristics for UI, website, email, and slides — hierarchy, contrast, CTAs, density, and more. No API key required.',
                ],
                [
                    'icon' => 'document-text',
                    'title' => 'Your DESIGN.md first',
                    'body' => 'When your agent sends your project’s DESIGN.md, or you’ve saved design rules on Your reviews, the checklist leads with your firmest rules and vision names the rule a shot breaks. Those hints start with “DESIGN.md:”, and the review shows a DESIGN.md chip.',
                ],
                [
                    'icon' => 'eye',
                    'title' => 'Optional vision regions',
                    'body' => 'With ANTHROPIC_API_KEY or OPENAI_API_KEY (or an OpenAI-compatible base URL), vision findings can carry an area and render as dashed markers on the screenshot.',
                ],
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Human marks stay authoritative',
                    'body' => 'Solid yellow marks are yours. Second opinion never auto-flips status. Overlaps enrich under related_pin — they do not invent a conflicting must-fix.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Agent subagent path',
                    'body' => 'Agents can call add_findings before you open the link. Those land with an Agent badge as suggestions — still not decisions.',
                ],
            ],
            'checklist' => [
                'Human marks = intent (must-fix, nit, question, keep, …) exposed as work_packets.pins',
                'Findings = suggestions only (suggestion / a11y / polish)',
                'Checklist findings have no area; only vision findings may point at a region',
                'design_rules on create_review (your DESIGN.md) puts your own rules first, and passes keep them',
                'request_second_opinion re-runs checklist (+ vision when keyed)',
                'Open the craft chip on a review to see which public lenses apply for that type',
            ],
            'sources' => true,
            'sources_intro' => 'Type-aware second opinion draws on published craft principles. Findings are ReviseMy hints — not quotes, reviews, or endorsements from the people or organizations behind these works.',
            'faq' => [
                [
                    'q' => 'Do I need an API key for second opinion?',
                    'a' => 'Not for the free checklist — it runs on every upload. Vision region hints need your own Anthropic or OpenAI key on the server (BYOK). Optional REVISEMY_VISION_PROVIDER and OpenAI-compatible base URLs for Ollama and similar.',
                ],
                [
                    'q' => 'Can second opinion approve a review?',
                    'a' => 'No. Only you approve or request changes. Agents follow next_action from your decision and must treat second_opinion as hints.',
                ],
                [
                    'q' => 'Does it use my DESIGN.md?',
                    'a' => 'Yes, when your agent passes it as design_rules or you save rules on Your reviews. Your rules are checked first. When a mark states a lasting preference, your agent offers to add it to DESIGN.md, and asks you first.',
                ],
                [
                    'q' => 'How do I refresh hints?',
                    'a' => 'Use Refresh second opinion on the review page, or have the agent call request_second_opinion.',
                ],
                [
                    'q' => 'Are these designers reviewing my UI?',
                    'a' => 'No. ReviseMy distills public craft principles into checklist and vision hints. The craft chip and this page name the sources; nobody outside your loop is reviewing or endorsing your screenshots.',
                ],
            ],
        ],

        'board' => [
            'slug' => 'board',
            'path' => '/board',
            'label' => 'Board',
            'icon' => 'queue-list',
            'title' => 'Design review board — track marks from open to verified',
            'description' => 'Track every mark from open to verified. Your agent attaches before and after shots as it fixes; only you verify. Each pass stays easy to scan.',
            'keywords' => [
                'design review board',
                'mark status board',
                'open resolved verified',
                'before after evidence',
                'multi-pass design review',
                'AI agent design checklist',
            ],
            'headline' => 'Track every mark from open to verified',
            'subheadline' => 'The owner board is your checklist across passes: agents resolve with notes and after shots; you verify when it actually looks right — then approve or open the next pass.',
            'features_heading' => 'What the board does',
            'checklist_heading' => 'How status stays honest',
            'problem' => 'Marks get lost in chat threads. “Fixed?” and “looks right?” blur together. Without a shared status board, agents claim done and humans re-explain the same pixel notes on every pass.',
            'loop' => 'You mark on the review. Agents call resolve_marks with notes and optional after images. You open /r/{token}/board to move marks through open → in progress → resolved → verified. When outstanding marks clear, approve — or request changes so the agent opens the next pass with parent_id and fresh captures.',
            'loop_steps' => [
                [
                    'text' => 'Mark regions on the review with must-fix, nice to have, question, or keep.',
                ],
                [
                    'command' => 'resolve_marks',
                    'text' => 'lets the agent move a mark to in progress or resolved — with a note and optional after image.',
                ],
                [
                    'text' => 'You verify (or reopen) on the board. Agents never verify for you.',
                ],
                [
                    'command' => 'create_review',
                    'text' => 'with',
                    'after' => [
                        ['type' => 'text', 'value' => ' '],
                        ['type' => 'command', 'value' => 'parent_id'],
                        ['type' => 'text', 'value' => ' opens the next pass after you request changes.'],
                    ],
                ],
            ],
            'product_shots' => [
                'stylized' => 'board',
                'alt' => 'ReviseMy board — marks moving from open to resolved to verified across a review pass',
            ],
            'features' => [
                [
                    'icon' => 'queue-list',
                    'title' => 'Four clear columns',
                    'body' => 'Open, in progress, resolved, and verified — so “agent is working,” “agent says done,” and “human signed off” never look the same.',
                ],
                [
                    'icon' => 'photo',
                    'title' => 'Before / after on the mark',
                    'body' => 'Agents can attach evidence when they resolve. You verify against the pixels, not a chat summary.',
                ],
                [
                    'icon' => 'arrows-right-left',
                    'title' => 'Pass ledger + verify focus',
                    'body' => 'Multi-pass reviews show a revision ledger. When the agent resolves a batch, the review surfaces “Awaiting your verify” so you can verify-all or reopen.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Owner-only board',
                    'body' => 'The board is an owner tool on the secret review token. Guests leave suggestions on the review; they do not run the board.',
                ],
            ],
            'checklist' => [
                'Open → in progress → resolved → verified is the lifecycle',
                'Agents may set in_progress and resolved via resolve_marks — never verified',
                'Only you verify or reopen; that gate keeps “looks right” human',
                'Request changes when you want a new pass with fresh captures',
                'Outstanding marks drive next_action until the board is clear enough to approve',
                'Pass ledger and verify focus live on the review page; the board is still best for scanning columns',
            ],
            'faq' => [
                [
                    'q' => 'How is the board different from the review page?',
                    'a' => 'The review page is where you mark on the pixels, answer questions, triage second opinion / guest hints, verify resolved marks, and decide approve / request changes. The board (/r/{token}/board) is the status checklist across all marks — better for scanning columns.',
                ],
                [
                    'q' => 'Can guests use the board?',
                    'a' => 'No. Guests use the guest link for suggestions on the review. The board is owner-only on the review token.',
                ],
                [
                    'q' => 'What should the agent call when a mark is fixed?',
                    'a' => 'resolve_marks with the mark id, status in_progress then resolved, a note, and optional after images. You still verify on the board or via Awaiting your verify on the review.',
                ],
                [
                    'q' => 'What’s a pass?',
                    'a' => 'A pass is one capture set in the loop. Request changes and the agent opens pass 2+ with create_review + parent_id and new screenshots. The pass ledger keeps decisions and mark counts readable across those rounds.',
                ],
            ],
        ],

        'guest-links' => [
            'slug' => 'guest-links',
            'path' => '/guest-links',
            'label' => 'Guest links',
            'icon' => 'link',
            'mark_icon' => 'g',
            'title' => 'Guest links — another set of eyes, no accounts',
            'description' => 'Share a private guest link for another set of eyes — no accounts. Guests suggest, your marks decide. Links expire in 7 days, 14, never or on a date.',
            'keywords' => [
                'guest design review link',
                'guest share link',
                'client design review',
                'no account design review',
                'guest suggestions',
                'review link expiry',
            ],
            'headline' => 'Another set of eyes — without handing over the board',
            'subheadline' => 'Share a private guest link when you want a teammate or client on the capture — no accounts. Guests leave suggestions only; your marks stay authoritative. Expiry defaults to 7 days, or pick 14 days, never, or a custom date.',
            'features_heading' => 'How guest links work',
            'checklist_heading' => 'Owner vs guest at a glance',
            'problem' => 'You want a second human on the pixels without giving them approve / request-changes power — and without creating accounts. Chat threads blur who decided what; a shared owner link lets anyone decide.',
            'loop' => 'On the owner review (/r/{token}), open Share to copy or regenerate the guest link (/r/{share_token}). Guests leave named suggestions (G#) and can comment on marks. You accept or dismiss; only owner marks (M#) and decisions drive next_action. The board stays owner-only.',
            'loop_steps' => [
                [
                    'text' => 'Open Share on the owner review and copy the guest link — or regenerate if the old one leaked.',
                ],
                [
                    'text' => 'Set expiry to 7 days (default), 14 days, never, or a custom date. Expired links show a clear message.',
                ],
                [
                    'text' => 'Guests leave G# suggestions and optional comments. You accept what belongs in the brief; your M# marks stay authoritative.',
                ],
            ],
            'features' => [
                [
                    'icon' => 'link',
                    'title' => 'Private guest link',
                    'body' => 'A separate share_token URL — not the owner review link. No accounts for guests. Regenerating rotates the link and resets expiry to seven days.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Suggestions only (G#)',
                    'body' => 'Guest notes are labeled G#. They never approve, request changes, or verify. Your M# marks still run the show.',
                ],
                [
                    'icon' => 'queue-list',
                    'title' => 'Board stays owner-only',
                    'body' => 'Guests work on the review capture. Status columns and verification live on /r/{token}/board for the owner.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Expiry you control',
                    'body' => 'Default seven days. Switch to 14 days, never, expire now, or pick a custom end date — then regenerate when access should end early.',
                ],
            ],
            'checklist' => [
                'Owner /r/{token} — mark, decide, guest links, board',
                'Guest /r/{share_token} — suggestions and comments only',
                'M# = authoritative marks; G# = guest; S# = second opinion',
                'Regenerate rotates the guest token; old links stop working',
                'Need a reviewer path? See /for/reviewers and /board',
            ],
            'faq' => [
                [
                    'q' => 'Can a guest approve the review?',
                    'a' => 'No. Guests leave suggestions. Only the owner link can approve, request changes, verify marks, or manage guest links.',
                ],
                [
                    'q' => 'What happens when a guest link expires?',
                    'a' => 'The guest URL shows that the link expired. Extend or clear expiry from Share on the owner review, or regenerate a fresh link.',
                ],
                [
                    'q' => 'Is the owner review link different?',
                    'a' => 'Yes. Anyone with the owner token can mark and decide — treat it like a password. Use a guest link when you want eyes without that power.',
                ],
            ],
        ],

        'changelog' => [
            'slug' => 'changelog',
            'path' => '/changelog',
            'label' => 'Changelog',
            'title' => 'ReviseMy changelog — SemVer release notes',
            'description' => 'Versioned release notes for ReviseMy. Semantic Versioning releases for the human-in-the-loop design checkup loop, connectors, and review board.',
            'keywords' => [
                'ReviseMy changelog',
                'release notes',
                'SemVer',
                'design review updates',
            ],
            'headline' => 'What shipped',
            'subheadline' => 'Release notes for ReviseMy — newest first.',
            'changelog' => true,
        ],

    ],

];
