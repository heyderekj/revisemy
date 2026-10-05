<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connecting an assistant
    |--------------------------------------------------------------------------
    |
    | The one list of ways in, read by the connect hub (/connect, the
    | homepage's Setup, /connectors) and by TryTokenService's prompts. Each
    | host leads with its fastest honest path:
    |
    |   oauth    paste the address and click Connect (Claude, ChatGPT, Grok, Claude Code)
    |   deeplink one click installs it, then sign in
    |   token    needs a try token, because the host can't sign in yet
    |
    | {url} and {token} are filled in where shown. Facts per host follow
    | Koati's connect page (site/src/pages/assistant.astro), which cites each
    | provider's docs. Copy rules: docs/positioning.md.
    |
    */
    'connect' => [
        'claude' => [
            'name' => 'Claude',
            'icon' => 'claude',
            'mode' => 'oauth',
            'where' => 'Claude on the web, desktop or phone',
            'steps' => [
                'In Claude, open Customize → Connectors and choose Add custom connector.',
                'Name it ReviseMy and paste the address.',
                'Claude opens ReviseMy. Click Connect, and you’re done.',
            ],
            'inline' => true,
            'prompt' => "ReviseMy is connected. Run a design checkup on the work I just changed. Call create_review with exactly one source: capture_url true and page_url for a public page, or images as data URLs for local UI. The review board renders inline here. Also paste review_url in your reply. Poll get_review and follow next_action until I approve. Do not mark your own work verified.",
        ],
        'chatgpt' => [
            'name' => 'ChatGPT',
            'icon' => 'chatgpt',
            'mode' => 'oauth',
            'where' => 'The ChatGPT app',
            'steps' => [
                'In ChatGPT, open Settings → Connectors and add a custom connector.',
                'Paste the address.',
                'ChatGPT opens ReviseMy. Click Connect, and you’re done.',
            ],
            'note' => 'The ChatGPT app only connects by signing in, so there’s no token to paste.',
            'prompt' => "ReviseMy is connected. Run a design checkup on the work I just changed. Call create_review with exactly one source: capture_url true and page_url for a public page, or images as data URLs for local UI. ChatGPT does not render the inline board, so paste review_url in your reply and wait. Poll get_review and follow next_action until I approve.",
        ],
        'cursor' => [
            'name' => 'Cursor',
            'icon' => 'cursor',
            'mode' => 'deeplink',
            'where' => 'Cursor’s agent',
            'steps' => [
                'Click Add to Cursor and confirm in Cursor.',
                'Cursor opens ReviseMy to sign in. Click Connect.',
            ],
            'prompt' => "ReviseMy is connected. Run a design checkup on the work I just changed. Call create_review with exactly one source: images as data URLs for the local UI, or capture_url true and page_url for a public page. Paste review_url in your reply. Poll get_review and follow next_action until I approve. Human marks in work_packets.pins are authoritative.",
        ],
        'vscode' => [
            'name' => 'VS Code',
            'icon' => 'vscode',
            'mode' => 'deeplink',
            'where' => 'Copilot’s agent in VS Code',
            'steps' => [
                'Click Add to VS Code and confirm in VS Code.',
                'VS Code opens ReviseMy to sign in. Click Connect.',
            ],
            'inline' => true,
            'prompt' => "ReviseMy is connected. Run a design checkup on the work I just changed. Call create_review with exactly one source: images as data URLs for the local UI, or capture_url true and page_url for a public page. The review can render inline. Also paste review_url. Poll get_review and follow next_action until I approve.",
        ],
        'claude-code' => [
            'name' => 'Claude Code',
            'icon' => 'claude',
            'mode' => 'oauth',
            'where' => 'Your terminal',
            'command' => 'claude mcp add --transport http revisemy {url}',
            'steps' => [
                'Run the command in your project.',
                'Run /mcp, choose revisemy, and sign in. Click Connect.',
            ],
            'prompt' => "ReviseMy is connected. Run a design checkup on the work I just changed. Call create_review with exactly one source: images as data URLs for local UI, or capture_url true and page_url for a public page. Paste review_url in your reply — this host has no inline board. Poll get_review and follow next_action until I approve.",
        ],
        'grok' => [
            'name' => 'Grok',
            'icon' => 'grok',
            'mode' => 'oauth',
            'where' => 'Grok on the web, iOS or Android',
            'steps' => [
                'In Grok, open Connectors → New Connector and choose Custom.',
                'Name it ReviseMy and paste the address.',
                'Grok opens ReviseMy. Click Connect, and you’re done.',
            ],
            'note' => 'The custom connector signs in. It has no field for a try token. The command line can still send one as a Bearer header.',
            'prompt' => "ReviseMy is connected. Run a design checkup on the work I just changed. Call create_review with exactly one source: capture_url true and page_url for a public page, or images as data URLs for local UI. Grok does not render the inline board, so the reply must include the review_url on its own line. Poll get_review and follow next_action until I approve.",
        ],
        'muse' => [
            'name' => 'Muse',
            'icon' => 'muse',
            'mode' => 'token',
            'where' => 'Meta’s agent',
            'command' => "Build a custom connector to ReviseMy and save this credential. Do not ask me for another key.\n\nName: ReviseMy\nMCP address: {url}\nTransport: HTTP\nAuthorization: Bearer {token}\n\nAfter it is connected, confirm the tools create_review and get_review are available, then stop.\n\nWhen I ask for a design checkup: call create_review with exactly one source. Use capture_url true plus page_url for a public site. Use images as data URLs for local UI. Never put a page URL in images. Muse does not render an inline review board, so paste review_url in the reply on its own line. Then poll get_review and follow next_action until I approve. Human marks are authoritative. Do not mark your own work verified.",
            'steps' => [
                'Get a try token.',
                'Paste the message below to Muse. It keeps the token in its own credential store.',
            ],
            'note' => 'Signing in to a connector in Muse is still rough, so a token is the dependable way for now.',
        ],
        'codex' => [
            'name' => 'Codex',
            'icon' => 'codex',
            'mode' => 'token',
            'where' => 'OpenAI’s coding agent',
            'command' => "[mcp_servers.revisemy]\nurl = \"{url}\"\nbearer_token_env_var = \"REVISEMY_TOKEN\"",
            'steps' => [
                'Get a try token, and keep it in a variable with the line below.',
                'Add the block to ~/.codex/config.toml.',
                'Paste the prompt to Codex once the server is listed.',
            ],
            'prompt' => "ReviseMy is connected at {url} with bearer token {token}. Confirm create_review and get_review are available. When I ask for a checkup, call create_review with exactly one source: images as data URLs for local UI, or capture_url true and page_url for a public page. Paste review_url in your reply. Poll get_review and follow next_action until I approve.",
        ],
    ],

    /*
    | The first thing to ask, once it's connected. Proves the connection and
    | starts the loop. {host} is the host's name.
    */
    'first_prompt' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with exactly one source (capture_url true and page_url for a public page, or images as data URLs for local UI). Paste the review_url in your reply even if the board also renders inline. Poll get_review and follow next_action until I approve.',

];
