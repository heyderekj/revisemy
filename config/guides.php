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
                        'In ChatGPT, open Settings → Connectors (or Custom GPT → Actions for REST)',
                        'Or use Ask agent on the homepage for a prompt with URL + Bearer filled in',
                        'Paste the MCP URL and Authorization: Bearer {try_token}',
                        'Paste a checkup prompt asking it to run create_review and follow next_action',
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Use create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF), share the review_url if you get one, wait for my marks, then poll get_review and follow next_action until I approve.',
                ],
                [
                    'id' => 'claude',
                    'label' => 'Claude',
                    'mode' => 'Inline (Desktop) · Link (Code)',
                    'body' => 'Claude Desktop can render the review inline via MCP Apps. Use Settings → Developer → Edit Config with the mcp-remote JSON (Bearer try tokens do not fit Connectors → Add custom connector, which is OAuth-oriented). Claude Code is CLI-only and shares a `review_url` instead. Homepage Ask agent generates prompts that include your config.',
                    'steps' => [
                        'Desktop: Settings → Developer → Edit Config, merge the mcp-remote JSON, quit and reopen Claude — or paste the Desktop agent setup prompt',
                        'Claude Code: run `claude mcp add --transport http …` (or paste the Code agent setup prompt)',
                        'Paste a checkup prompt (or use design_checkup_loop) and mark / approve on Desktop inline or via review_url on Code',
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source. Open the review inline if available, otherwise give me the review_url. Wait for my marks, then follow next_action until I approve. Prefer the design_checkup_loop prompt if available.',
                ],
                [
                    'id' => 'copilot',
                    'label' => 'Copilot',
                    'mode' => 'Inline MCP Apps',
                    'body' => 'Paste or merge the servers JSON into Copilot MCP settings (user or workspace mcp.json). After create_review, the review renders inline in Copilot Chat so you can mark and approve without leaving the editor.',
                    'steps' => [
                        'Open Copilot → MCP — or paste Ask agent from the homepage',
                        'Paste the try-token Copilot config from the homepage under servers',
                        'Paste a checkup prompt asking Copilot to call create_review and open the review inline',
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF). Open the review inline so I can mark and approve, then follow next_action until I’m done. Prefer the design_checkup_loop prompt if available.',
                ],
                [
                    'id' => 'cursor',
                    'label' => 'Cursor',
                    'mode' => 'Link via review_url',
                    'body' => 'Cursor agents use MCP tools in the IDE. After create_review, the agent shares a review_url — open it in the browser to mark feedback and approve. No inline MCP Apps UI in Cursor yet; the loop is the same.',
                    'steps' => [
                        'Open Cursor Settings → MCP — or paste Ask agent so Cursor edits ~/.cursor/mcp.json',
                        'Paste mcpServers JSON (or merge into ~/.cursor/mcp.json)',
                        'Paste a checkup prompt asking the agent to call create_review and share the review_url',
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots as data URLs for localhost, or public URL + capture_url). Give me the review_url to mark and approve, then poll get_review and follow next_action until I approve. Prefer the design_checkup_loop prompt if available.',
                ],
                [
                    'id' => 'grok',
                    'label' => 'Grok',
                    'mode' => 'Custom connector · Link',
                    'body' => 'Add ReviseMy as a custom MCP connector at grok.com/connectors. Paste the MCP URL and Bearer Authorization header (not the full JSON). After create_review, open the review_url to mark and approve (link workflow).',
                    'steps' => [
                        'Go to grok.com/connectors → New Connector → Custom — or paste Ask agent for guided steps',
                        'Paste the public MCP URL and authorize with the Bearer token',
                        'Paste a checkup prompt asking Grok to call create_review and share the review_url',
                    ],
                    'prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source, give me the review_url to mark and approve, then poll get_review and follow next_action until I approve.',
                ],
            ],
            'features' => [
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'MCP Apps vs link hosts',
                    'body' => 'Claude Desktop, claude.ai, and Copilot can render the review inline. Cursor, Claude Code, and Grok use the review_url tab — same marks, board, and next_action.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'One try token, any host',
                    'body' => 'Homepage try tokens mint a Sanctum Bearer for /mcp/revisemy and the REST API. Host packaging is install UX on top of the same Cloud-hosted endpoint.',
                ],
                [
                    'icon' => 'arrow-path',
                    'title' => 'Webhooks for CI/CD',
                    'body' => 'Pass webhook_url to create_review. ReviseMy POSTs a signed review.decided event when you approve or request changes — gate pipelines instead of polling.',
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
            'problem' => 'One-shot AI critique often sounds decisive — and agents treat it that way. You need optional design hints that stay labeled as suggestions while humans remain the authority on approve / request-changes.',
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

    ],

];
