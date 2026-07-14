<?php

return [

    'pages' => [

        'chatgpt' => [
            'slug' => 'chatgpt',
            'label' => 'ChatGPT',
            'icon' => 'chatgpt',
            'teaser' => 'Remote MCP or REST — paste URL + Bearer and run create_review.',
            'title' => 'ReviseMy for ChatGPT — MCP design checkup',
            'description' => 'Connect ReviseMy to ChatGPT over remote MCP or a Custom GPT Action. Get a try token, add the connector, and run a human-in-the-loop design checkup with create_review.',
            'keywords' => [
                'ChatGPT MCP',
                'ChatGPT design review',
                'Custom GPT design checkup',
                'ReviseMy ChatGPT',
            ],
            'headline' => 'Design checkup inside ChatGPT',
            'subheadline' => 'Add ReviseMy as a remote MCP connector (or Custom GPT Action for REST). Use the MCP URL and Bearer try token from the homepage — then ask ChatGPT to run create_review and follow next_action.',
            'features_heading' => 'What you get with ChatGPT',
            'checklist_heading' => 'Quick setup',
            'problem' => 'ChatGPT can critique UI in text, but it cannot leave structured marks on pixels or wait for a human approve / request-changes gate. You need a connector that hands off to a real review link.',
            'loop' => 'Get a try token on the homepage, add the MCP URL and Authorization: Bearer token in ChatGPT Connectors (or Actions for REST), then paste a checkup prompt. When the host supports MCP Apps the review can render inline; otherwise ChatGPT shares the review_url.',
            'features' => [
                [
                    'icon' => 'puzzle-piece',
                    'title' => 'Remote MCP or REST',
                    'body' => 'ChatGPT’s connector UI takes URL + Bearer — not a full JSON paste. Homepage Ask agent fills those fields into a prompt for you.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'Inline when available, link otherwise',
                    'body' => 'If the host supports MCP Apps, the review can open in chat. Otherwise you get a review_url — same loop either way.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Same tools as every host',
                    'body' => 'create_review, get_review, next_action — host-agnostic payloads so you are not locked to ChatGPT.',
                ],
            ],
            'checklist' => [
                'Get a free try token on the homepage',
                'ChatGPT → Settings → Connectors (or Custom GPT → Actions for REST)',
                'Paste MCP URL + Authorization: Bearer {try_token}',
                'Ask it to run create_review and follow next_action',
            ],
            'faq' => [
                [
                    'q' => 'Where is the paste-ready config?',
                    'a' => 'See the ChatGPT section on /connectors#chatgpt, or use Ask agent on the homepage after you create a try token.',
                ],
                [
                    'q' => 'Does ChatGPT support MCP Apps inline?',
                    'a' => 'When the host supports MCP Apps, yes. Otherwise the agent shares review_url. Details on /mcp-apps.',
                ],
            ],
            'connector_anchor' => 'chatgpt',
        ],

        'claude' => [
            'slug' => 'claude',
            'label' => 'Claude',
            'icon' => 'claude',
            'teaser' => 'Inline on Desktop · review_url on Claude Code.',
            'title' => 'ReviseMy for Claude — Desktop MCP Apps and Claude Code',
            'description' => 'Connect ReviseMy to Claude Desktop for inline MCP Apps review, or Claude Code via review_url. Paste mcp-remote config or claude mcp add, then run the design checkup loop.',
            'keywords' => [
                'Claude MCP',
                'Claude Desktop MCP Apps',
                'Claude Code design review',
                'ReviseMy Claude',
            ],
            'headline' => 'Design checkup with Claude',
            'subheadline' => 'Claude Desktop can render the review inline via MCP Apps. Claude Code is CLI-only and shares a review_url. Same try token, same create_review loop — pick the surface that matches how you work.',
            'features_heading' => 'Desktop vs Code',
            'checklist_heading' => 'Quick setup',
            'problem' => 'Claude can describe UI issues, but structured human marks and a multi-pass board need a connector. Desktop and Code need different paste paths for the same MCP server.',
            'loop' => 'Get a try token. On Desktop, merge mcp-remote JSON under Settings → Developer → Edit Config. On Code, run claude mcp add --transport http. Prefer design_checkup_loop when available; mark inline on Desktop or via review_url on Code.',
            'features' => [
                [
                    'icon' => 'puzzle-piece',
                    'title' => 'Inline on Desktop',
                    'body' => 'MCP Apps open the review in a sandboxed iframe so you mark and decide without leaving Claude.',
                ],
                [
                    'icon' => 'command-line',
                    'title' => 'Link on Claude Code',
                    'body' => 'CLI hosts share review_url. Open it in a browser — next_action still drives the agent.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'Bearer try tokens',
                    'body' => 'Use Developer → Edit Config (mcp-remote). Connectors → Add custom connector is OAuth-oriented and does not fit Bearer try tokens today.',
                ],
            ],
            'checklist' => [
                'Get a free try token on the homepage',
                'Desktop: Settings → Developer → Edit Config, merge mcp-remote JSON, quit and reopen',
                'Code: claude mcp add --transport http with your MCP URL + Bearer',
                'Run create_review (or design_checkup_loop) and follow next_action',
            ],
            'faq' => [
                [
                    'q' => 'Where is the full Claude setup?',
                    'a' => 'See /connectors#claude for Desktop vs Code paths and copyable prompts.',
                ],
                [
                    'q' => 'What is MCP Apps?',
                    'a' => 'The inline review UI extension. Read /mcp-apps for what renders in chat vs what stays on review_url.',
                ],
            ],
            'connector_anchor' => 'claude',
        ],

        'copilot' => [
            'slug' => 'copilot',
            'label' => 'Copilot',
            'icon' => 'copilot',
            'teaser' => 'Inline MCP Apps design review in Copilot chat.',
            'title' => 'ReviseMy for GitHub Copilot — MCP Apps design checkup',
            'description' => 'Connect ReviseMy to GitHub Copilot over MCP Apps for inline human-in-the-loop design review. Paste the MCP config, run create_review, and mark in chat.',
            'keywords' => [
                'Copilot MCP',
                'GitHub Copilot design review',
                'MCP Apps Copilot',
                'ReviseMy Copilot',
            ],
            'headline' => 'Design checkup inside Copilot',
            'subheadline' => 'Copilot can host the ReviseMy review inline via MCP Apps. Add the MCP endpoint with your Bearer try token, ask for a design checkup, and mark regions without leaving chat.',
            'features_heading' => 'What you get with Copilot',
            'checklist_heading' => 'Quick setup',
            'problem' => 'Copilot ships code fast; visual sign-off still needs a human on the pixels. Inline MCP Apps keep that gate in the same chat where the agent is working.',
            'loop' => 'Get a try token, add ReviseMy as an MCP server in Copilot, then ask it to create_review. The review opens inline when MCP Apps is available; follow next_action until you approve.',
            'features' => [
                [
                    'icon' => 'puzzle-piece',
                    'title' => 'Inline MCP Apps',
                    'body' => 'Mark, verify, and decide in the sandboxed review UI inside Copilot chat.',
                ],
                [
                    'icon' => 'check',
                    'title' => 'Human-only decide tools',
                    'body' => 'add_mark and decide_review power the iframe — agents poll get_review instead.',
                ],
                [
                    'icon' => 'link',
                    'title' => 'Full board still on the web',
                    'body' => 'Guest share, rich comments, and drag columns stay on review_url / board_url when you need them.',
                ],
            ],
            'checklist' => [
                'Get a free try token on the homepage',
                'Add the MCP URL + Bearer token in Copilot MCP settings',
                'Ask for a design checkup with create_review',
                'Mark inline; agent follows next_action',
            ],
            'faq' => [
                [
                    'q' => 'Where is the Copilot paste path?',
                    'a' => 'See /connectors#copilot for host-specific steps and prompts.',
                ],
            ],
            'connector_anchor' => 'copilot',
        ],

        'cursor' => [
            'slug' => 'cursor',
            'label' => 'Cursor',
            'icon' => 'cursor',
            'teaser' => 'MCP in Cursor — agent shares review_url for human marks.',
            'title' => 'ReviseMy for Cursor — MCP design checkup',
            'description' => 'Connect ReviseMy to Cursor over HTTP MCP. Your coding agent creates reviews and shares a review_url; you mark and approve in the browser while Cursor follows next_action.',
            'keywords' => [
                'Cursor MCP',
                'Cursor design review',
                'AI coding agent design checkup',
                'ReviseMy Cursor',
            ],
            'headline' => 'Design checkup from Cursor',
            'subheadline' => 'Add ReviseMy as an MCP server in Cursor. After UI work, ask the agent to run create_review — it shares a review_url for you to mark. Cursor polls get_review and follows next_action until you approve.',
            'features_heading' => 'What you get with Cursor',
            'checklist_heading' => 'Quick setup',
            'problem' => 'Cursor agents often claim UI is done from DOM or screenshots in chat. Without a human mark surface and next_action gate, “looks fine” is not a review.',
            'loop' => 'Get a try token, add the MCP config in Cursor, then ask for a design checkup after visual changes. Open the review_url, mark what matters, approve or request changes — the agent reads structured work packets.',
            'features' => [
                [
                    'icon' => 'command-line',
                    'title' => 'Link workflow',
                    'body' => 'Cursor shares review_url (MCP Apps inline is for other hosts). Same tools, browser for human marks.',
                ],
                [
                    'icon' => 'cursor-arrow-rays',
                    'title' => 'Built for coding agents',
                    'body' => 'create_review after UI changes; resolve_marks with notes and after shots; never verify for you.',
                ],
                [
                    'icon' => 'bolt',
                    'title' => 'Optional webhooks',
                    'body' => 'Pass webhook_url when you want CI to gate on review.decided instead of polling — see /webhooks.',
                ],
            ],
            'checklist' => [
                'Get a free try token on the homepage',
                'Add MCP server config in Cursor (URL + Bearer)',
                'Ask the agent to run create_review after UI work',
                'Open review_url, mark, decide; agent follows next_action',
            ],
            'faq' => [
                [
                    'q' => 'Where is the Cursor JSON?',
                    'a' => 'See /connectors#cursor and the homepage Ask agent flow after you create a try token.',
                ],
            ],
            'connector_anchor' => 'cursor',
        ],

        'grok' => [
            'slug' => 'grok',
            'label' => 'Grok',
            'icon' => 'grok',
            'teaser' => 'MCP on Grok — review_url handoff for human marks.',
            'title' => 'ReviseMy for Grok — MCP design checkup',
            'description' => 'Connect ReviseMy to Grok over MCP. Create a try token, add the connector, and run a human-in-the-loop design checkup with review_url handoff.',
            'keywords' => [
                'Grok MCP',
                'Grok design review',
                'xAI connector design checkup',
                'ReviseMy Grok',
            ],
            'headline' => 'Design checkup with Grok',
            'subheadline' => 'Add ReviseMy as an MCP connector for Grok. The agent creates a review and shares a review_url; you mark and decide in the browser while Grok follows next_action.',
            'features_heading' => 'What you get with Grok',
            'checklist_heading' => 'Quick setup',
            'problem' => 'Grok can talk through UI issues, but structured marks and multi-pass verification need a dedicated review surface the agent can poll.',
            'loop' => 'Get a try token, add the MCP URL and Bearer token in Grok connectors, then ask for a design checkup. Open the review_url to mark; the agent reads get_review until you approve.',
            'features' => [
                [
                    'icon' => 'link',
                    'title' => 'review_url handoff',
                    'body' => 'Link hosts share the secret review URL. Mark in the browser — same next_action contract as every other connector.',
                ],
                [
                    'icon' => 'puzzle-piece',
                    'title' => 'Host-agnostic tools',
                    'body' => 'Tool names and payloads stay the same across ChatGPT, Claude, Copilot, Cursor, and Grok.',
                ],
                [
                    'icon' => 'eye',
                    'title' => 'Second opinion still optional',
                    'body' => 'Checklist runs free; vision hints need your API key. Human marks stay authoritative — see /second-opinion.',
                ],
            ],
            'checklist' => [
                'Get a free try token on the homepage',
                'Add ReviseMy in Grok connectors (URL + Bearer)',
                'Ask for create_review after visual work',
                'Open review_url, mark, decide; agent follows next_action',
            ],
            'faq' => [
                [
                    'q' => 'Where is the Grok setup detail?',
                    'a' => 'See /connectors#grok for steps and checkup prompts.',
                ],
            ],
            'connector_anchor' => 'grok',
        ],

    ],

];
