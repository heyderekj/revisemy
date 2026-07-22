<?php

return [

    'pages' => [

        'connectors' => [
            'slug' => 'connectors',
            'path' => '/connectors',
            'label' => 'Connectors',
            'icon' => 'puzzle-piece',
            'title' => 'Connect ReviseMy to your agent — ChatGPT, Claude, Copilot, Cursor, Grok',
            'description' => 'Plug ReviseMy into ChatGPT, Claude, Copilot, Cursor, or Grok over Laravel MCP. Get a try token, paste the config, and run a human-in-the-loop design checkup.',
            'keywords' => [
                'MCP connectors',
                'Cursor MCP',
                'Claude MCP',
                'ChatGPT connector',
                'Copilot MCP',
                'Grok connectors',
                'design review MCP',
            ],
            'headline' => 'Connect ReviseMy to the agent you already use',
            'subheadline' => 'One MCP endpoint and a Bearer try token. Paste the config into ChatGPT, Claude, Copilot, Cursor, or Grok — then run a design checkup without leaving your workflow.',
            'features_heading' => 'What you get once connected',
            'problem' => 'Agent setup docs often live on GitHub while the product lives on a marketing homepage. You need a crawlable place that explains which hosts support inline review, which share a link, and how to plug in without guessing.',
            'loop' => 'Get a free try token on the homepage, copy the host-specific MCP config, then ask your agent to call `create_review`. On MCP Apps hosts the review opens inline; on CLI and link hosts you open the `review_url`. Your agent polls `get_review` and follows `next_action` until you approve.',
            'loop_steps' => [
                [
                    'text' => 'Get a free try token on the homepage and copy the host-specific MCP config.',
                ],
                [
                    'command' => 'create_review',
                    'text' => 'opens inline on MCP Apps hosts, or shares a',
                    'after' => [
                        ['type' => 'text', 'value' => ' '],
                        ['type' => 'command', 'value' => 'review_url'],
                        ['type' => 'text', 'value' => ' on CLI and link hosts.'],
                    ],
                ],
                [
                    'command' => 'get_review',
                    'text' => 'polls until you decide;',
                    'after' => [
                        ['type' => 'text', 'value' => ' '],
                        ['type' => 'command', 'value' => 'next_action'],
                        ['type' => 'text', 'value' => ' tells the agent what to do next.'],
                    ],
                ],
            ],
            'hosts' => [
                [
                    'id' => 'chatgpt',
                    'label' => 'ChatGPT',
                    'mode' => 'Remote MCP or REST',
                    'body' => 'Add ReviseMy as a remote MCP connector (or Custom GPT Action for REST). Use the MCP URL and Bearer token from your try token — ChatGPT’s UI takes those fields, not a full JSON paste. When the host supports MCP Apps, the review can render inline; otherwise the agent shares a `review_url` link.',
                    'steps' => [
                        [
                            'label' => 'Open Connectors in ChatGPT',
                            'body' => 'Settings → Connectors (or Custom GPT → Actions for REST). Add a remote MCP connector named revisemy — ChatGPT’s UI takes URL + Bearer fields, not a full JSON paste.',
                        ],
                        [
                            'label' => 'Paste URL and Bearer token',
                            'body' => 'Use the MCP URL and Authorization: Bearer {try_token} from your homepage try token. Or use Ask agent on the homepage for a prompt with those values already filled in.',
                        ],
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Use create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF), share the review_url if you get one, wait for my marks, then poll get_review and follow next_action until I approve.',
                ],
                [
                    'id' => 'claude',
                    'label' => 'Claude',
                    'mode' => 'Inline (Desktop) · Link (Code)',
                    'body' => 'Claude Desktop can render the review inline via MCP Apps. Use Settings → Developer → Edit Config with the mcp-remote JSON (Bearer try tokens do not fit Connectors → Add custom connector, which is OAuth-oriented). Claude Code is CLI-only and shares a `review_url` instead. Homepage Ask agent generates prompts that include your config.',
                    'steps' => [
                        [
                            'label' => 'Claude Desktop — edit config',
                            'body' => 'Settings → Developer → Edit Config, merge the mcp-remote JSON, then quit and reopen Claude. Or paste the Desktop agent setup prompt from the homepage.',
                        ],
                        [
                            'label' => 'Claude Code — add HTTP MCP',
                            'body' => 'Run `claude mcp add --transport http …` with your try-token URL, or paste the Code agent setup prompt from the homepage. Code shares a review_url instead of inline UI.',
                        ],
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source. Open the review inline if available, otherwise give me the review_url. Wait for my marks, then follow next_action until I approve. Prefer the design_checkup_loop prompt if available.',
                ],
                [
                    'id' => 'copilot',
                    'label' => 'Copilot',
                    'mode' => 'Inline MCP Apps',
                    'body' => 'Paste or merge the servers JSON into Copilot MCP settings (user or workspace mcp.json). After create_review, the review renders inline in Copilot Chat so you can mark and approve without leaving the editor.',
                    'steps' => [
                        [
                            'label' => 'Open Copilot → MCP',
                            'body' => 'Open Copilot MCP settings (user or workspace mcp.json) — or paste Ask agent from the homepage for guided steps.',
                        ],
                        [
                            'label' => 'Paste the servers config',
                            'body' => 'Merge the try-token Copilot config from the homepage under servers so Copilot can call create_review and open the review inline.',
                        ],
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF). Open the review inline so I can mark and approve, then follow next_action until I’m done. Prefer the design_checkup_loop prompt if available.',
                ],
                [
                    'id' => 'cursor',
                    'label' => 'Cursor',
                    'mode' => 'Link via review_url',
                    'body' => 'Cursor agents use MCP tools in the IDE. After create_review, the agent shares a review_url — open it in the browser to mark feedback and approve. No inline MCP Apps UI in Cursor yet; the loop is the same.',
                    'steps' => [
                        [
                            'label' => 'Open Cursor Settings → MCP',
                            'body' => 'Or paste Ask agent so Cursor edits ~/.cursor/mcp.json for you.',
                        ],
                        [
                            'label' => 'Paste mcpServers JSON',
                            'body' => 'Paste the try-token config (or merge into ~/.cursor/mcp.json). After create_review, open the review_url in a browser to mark and approve.',
                        ],
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots as data URLs for localhost, or public URL + capture_url). Give me the review_url to mark and approve, then poll get_review and follow next_action until I approve. Prefer the design_checkup_loop prompt if available.',
                ],
                [
                    'id' => 'grok',
                    'label' => 'Grok',
                    'mode' => 'Custom connector · Link',
                    'body' => 'Add ReviseMy as a custom MCP connector at grok.com/connectors. Paste the MCP URL and Bearer Authorization header (not the full JSON). After create_review, open the review_url to mark and approve (link workflow).',
                    'steps' => [
                        [
                            'label' => 'Add a custom connector on Grok',
                            'body' => 'Go to grok.com/connectors → New Connector → Custom — or paste Ask agent from the homepage for guided steps.',
                        ],
                        [
                            'label' => 'Paste URL and Bearer token',
                            'body' => 'Paste the public MCP URL and authorize with Authorization: Bearer {try_token}. Grok uses the review_url link workflow after create_review.',
                        ],
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source, give me the review_url to mark and approve, then poll get_review and follow next_action until I approve.',
                ],
            ],
            'features' => [
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'MCP Apps vs link hosts',
                    'body' => 'Claude Desktop, claude.ai, and Copilot can render the review inline. Cursor, Claude Code, and Grok use the review_url tab — same marks, board, and next_action. Deep dive: /mcp-apps.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'One try token, any host',
                    'body' => 'Homepage try tokens mint a Sanctum Bearer for /mcp/revisemy and the REST API. Host packaging is install UX on top of the same Cloud-hosted endpoint. Thin landings: /for/chatgpt through /for/grok.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Webhooks for CI/CD',
                    'body' => 'Pass webhook_url to create_review. ReviseMy POSTs a signed review.decided event when you approve or request changes — gate pipelines instead of polling. Deep dive: /webhooks.',
                ],
                [
                    'icon' => 'code-bracket',
                    'title' => 'REST when MCP is not an option',
                    'body' => 'Custom GPT Actions and scripts can use POST /api/reviews with the same Bearer token. Tool names and payloads stay host-agnostic.',
                ],
            ],
            'faq' => [
                [
                    'q' => 'Do I need a ReviseMy account?',
                    'a' => 'No. Grab a free try token on the homepage, paste the MCP config, and go. Reviewers only need the secret /r/{token} link.',
                ],
                [
                    'q' => 'Where do I get the config JSON?',
                    'a' => 'On the homepage Try with your agent section. Generate a try token, pick your host tab, and copy the ready-made MCP URL, Bearer token, or JSON.',
                ],
                [
                    'q' => 'Can any MCP client connect?',
                    'a' => 'Yes. Any client that speaks HTTP MCP can use /mcp/revisemy with Authorization: Bearer {try_token}. Host-specific tabs are shortcuts, not a closed list.',
                ],
                [
                    'q' => 'Inline review vs review_url?',
                    'a' => 'MCP Apps hosts open the review in chat; CLI and link hosts share review_url. Same loop — see /mcp-apps.',
                ],
                [
                    'q' => 'How do decision webhooks work?',
                    'a' => 'Pass webhook_url on create_review. You get a signed review.decided POST when the human decides. Details on /webhooks.',
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
            'description' => 'ReviseMy second opinion runs a free design checklist and optional Claude or OpenAI vision on each screenshot. Hints only — human marks stay authoritative and never auto-flip approve or request-changes.',
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
                    'text' => 'runs the free checklist immediately.',
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
                    'text' => 'returns pins first;',
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
                    'icon' => 'eye',
                    'title' => 'Optional vision regions',
                    'body' => 'With ANTHROPIC_API_KEY or OPENAI_API_KEY (or an OpenAI-compatible base URL), vision findings can carry an area and render as dashed markers on the screenshot.',
                ],
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Human marks stay authoritative',
                    'body' => 'Solid rose marks are yours. Second opinion never auto-flips status. Overlaps enrich under related_pin — they do not invent a conflicting must-fix.',
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
            'description' => 'ReviseMy’s owner board tracks every mark open → in progress → resolved → verified. Agents attach before/after evidence; only humans verify. Multi-pass reviews stay scannable across shots.',
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
                    'title' => 'Scannable across passes',
                    'body' => 'Marks group by pass and shot so multi-shot, multi-pass reviews stay readable instead of a flat dump.',
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
            ],
            'faq' => [
                [
                    'q' => 'How is the board different from the review page?',
                    'a' => 'The review page is where you mark on the pixels and decide approve / request changes. The board (/r/{token}/board) is the status checklist across all marks and passes — better for scanning what’s left.',
                ],
                [
                    'q' => 'Can guests use the board?',
                    'a' => 'No. Guests use the guest link for suggestions on the review. The board is owner-only on the review token.',
                ],
                [
                    'q' => 'What should the agent call when a mark is fixed?',
                    'a' => 'resolve_marks with the mark id, status in_progress then resolved, a note, and optional after images. You still verify on the board.',
                ],
                [
                    'q' => 'What’s a pass?',
                    'a' => 'A pass is one capture set in the loop. Request changes and the agent opens pass 2+ with create_review + parent_id and new screenshots. The board keeps marks readable across those passes.',
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
            'description' => 'Share a private guest link when you want another set of eyes — no accounts. Guests leave G# suggestions; your M# marks stay authoritative. Set expiry to 7 days, 14 days, never, or a custom date.',
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

        'webhooks' => [
            'slug' => 'webhooks',
            'path' => '/webhooks',
            'label' => 'Webhooks',
            'icon' => 'bolt',
            'title' => 'Decision webhooks — gate CI on review.decided',
            'description' => 'Pass webhook_url to create_review and ReviseMy POSTs when the human approves or requests changes. HMAC-signed review.decided events so pipelines can gate without polling get_review.',
            'keywords' => [
                'design review webhook',
                'review.decided',
                'CI design approval',
                'MCP webhook',
                'HMAC webhook',
                'human in the loop CI',
            ],
            'headline' => 'Gate the pipeline when a human decides',
            'subheadline' => 'Polling get_review works. For CI/CD and event-driven agents, pass an HTTPS webhook_url on create_review — ReviseMy POSTs a signed review.decided payload when you approve or request changes.',
            'features_heading' => 'What gets delivered',
            'checklist_heading' => 'Integration checklist',
            'problem' => 'Agents that only poll burn cycles waiting on humans. Pipelines need a clear signal that the review was approved or sent back — without trusting an unsigned HTTP POST.',
            'loop' => 'Pass webhook_url when calling create_review (MCP or REST). When you decide, a queued job POSTs { event: review.decided, review: … } with HMAC headers. Follow-up passes inherit the parent webhook. Verify the signature with the owner review token before acting.',
            'loop_steps' => [
                [
                    'command' => 'create_review',
                    'text' => 'with an HTTPS',
                    'after' => [
                        ['type' => 'text', 'value' => ' '],
                        ['type' => 'command', 'value' => 'webhook_url'],
                        ['type' => 'text', 'value' => ' (and your usual source).'],
                    ],
                ],
                [
                    'text' => 'You approve or request changes on the review (or inline MCP App).',
                ],
                [
                    'text' => 'Your endpoint receives review.decided — check status and next_action, verify X-ReviseMy-Signature, then continue or stop the pipeline.',
                ],
            ],
            'features' => [
                [
                    'icon' => 'bolt',
                    'title' => 'Event: review.decided',
                    'body' => 'Payload includes decided_at and the same agent-shaped review object get_review returns — status, next_action, work packets.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'HMAC with the review token',
                    'body' => 'Headers: X-ReviseMy-Event, X-ReviseMy-Review, X-ReviseMy-Signature: sha256=<hmac>. Key = owner token from review_url. Verify before trusting.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Queued retries',
                    'body' => 'Delivery is queued (10s timeout, 3 attempts with backoff). Failures are logged and never block the human decision.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'Inherits on parent_id passes',
                    'body' => 'Follow-up create_review calls with parent_id keep the parent webhook so multi-pass loops stay wired.',
                ],
            ],
            'checklist' => [
                'HTTPS webhook_url on create_review (http only in local/testing)',
                'Verify HMAC-SHA256 of the raw body with the owner review token',
                'Branch on review.status: approved vs changes_requested',
                'Read review.next_action for what the agent should do next',
                'Prefer webhooks for CI gates; poll get_review when you need mid-loop progress',
            ],
            'faq' => [
                [
                    'q' => 'Do I still need to poll get_review?',
                    'a' => 'For mid-loop work (wait_for_human, applying marks) polling or MCP Apps still help. Use the webhook when you care about the decision moment — especially CI approve/block.',
                ],
                [
                    'q' => 'What if delivery fails?',
                    'a' => 'ReviseMy retries a few times with backoff, then logs the failure. The human decision already succeeded — your endpoint should be idempotent.',
                ],
                [
                    'q' => 'Where is the deep setup?',
                    'a' => 'See /connectors for host setup and docs/CONNECTORS.md for the full webhook contract.',
                ],
            ],
        ],

        'mcp-apps' => [
            'slug' => 'mcp-apps',
            'path' => '/mcp-apps',
            'label' => 'MCP Apps',
            'icon' => 'puzzle-piece',
            'title' => 'MCP Apps — inline design review in chat',
            'description' => 'On Claude Desktop, claude.ai, and Copilot, ReviseMy renders the review inline via MCP Apps. Cursor, Claude Code, and Grok share a review_url instead. Same loop either way.',
            'keywords' => [
                'MCP Apps',
                'inline design review',
                'Claude Desktop MCP',
                'Copilot MCP Apps',
                'review_url',
                'human in the loop MCP',
            ],
            'headline' => 'Mark and decide without leaving the chat',
            'subheadline' => 'Hosts that support MCP Apps render the review inline after create_review / get_review. CLI and link hosts still share review_url. The checkup loop is the same — only the surface changes.',
            'supported_agents_heading' => "Where it's available",
            'supported_agents_intro' => 'Hosts that can open the review inline in chat via MCP Apps.',
            'supported_agents' => [
                [
                    'id' => 'claude',
                    'label' => 'Claude Desktop',
                ],
                [
                    'id' => 'copilot',
                    'label' => 'Copilot',
                ],
            ],
            'features_heading' => 'Inline vs link',
            'checklist_heading' => 'What stays on the full review URL',
            'problem' => 'Copying a review link out of chat breaks flow. Some hosts can host an interactive UI in a sandboxed iframe; others are CLI-only. Agents need one protocol that works for both.',
            'loop' => 'create_review and get_review declare a ui://revisemy/review-app resource. MCP Apps hosts open it inline so you can mark, verify, and decide in chat. Cursor, Claude Code, and Grok share the review_url. Full owner workspace (comments, guest share, drag columns) stays on review_url / board_url.',
            'loop_steps' => [
                [
                    'command' => 'create_review',
                    'text' => 'returns a review; MCP Apps hosts open the inline UI.',
                ],
                [
                    'text' => 'You mark regions and approve or request changes in chat — or on the review_url if the host is link-only.',
                ],
                [
                    'command' => 'get_review',
                    'text' => 'gives the agent next_action; human-only app tools never get called by agents.',
                ],
            ],
            'features' => [
                [
                    'icon' => 'puzzle-piece',
                    'title' => 'Inline on MCP Apps hosts',
                    'body' => 'Claude Desktop, claude.ai, Copilot, and similar hosts render screenshot + board views in a sandboxed iframe.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'review_url on CLI / link hosts',
                    'body' => 'Cursor, Claude Code, Grok, and others share the secret link. Same marks, same next_action — open the URL in a browser.',
                ],
                [
                    'icon' => 'users',
                    'title' => 'Human-only app tools',
                    'body' => 'add_mark, decide_review, and verify_mark power the inline UI (Visibility::App). Agents never call them — they poll get_review.',
                ],
                [
                    'icon' => 'queue-list',
                    'title' => 'Full workspace still on the web',
                    'body' => 'Comment threads, guest link management, drag-and-drop columns, second-opinion triage, and title edit stay on review_url / board_url.',
                ],
            ],
            'checklist' => [
                'Inline: Claude Desktop / claude.ai / Copilot (when MCP Apps is enabled)',
                'Link: Cursor, Claude Code, Grok — open review_url',
                'Agents follow next_action; they do not call add_mark or decide_review',
                'Use /connectors#{host} for paste-ready setup',
                'Parity: MCP app chrome ships with the same review loop as the web board',
            ],
            'faq' => [
                [
                    'q' => 'Does inline replace the review URL?',
                    'a' => 'No. Inline covers the human mark / decide loop in chat. Owner tools like guest share and rich comments still live on the full review and board URLs.',
                ],
                [
                    'q' => 'Can my agent call decide_review?',
                    'a' => 'No. Those tools are human-only for the MCP App. Agents use get_review and next_action.',
                ],
                [
                    'q' => 'Where do I set up each host?',
                    'a' => 'Start at /connectors, or jump to a host landing under /for/chatgpt, /for/claude, /for/copilot, /for/cursor, or /for/grok.',
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
