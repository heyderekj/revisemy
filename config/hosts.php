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
        ],
        'muse' => [
            'name' => 'Muse',
            'icon' => 'muse',
            'mode' => 'token',
            'where' => 'Meta’s agent',
            'command' => "Build a custom connector to ReviseMy.\nAddress: {url}\nAuthorization: Bearer {token}",
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
                'Add this to ~/.codex/config.toml.',
            ],
        ],
    ],

    /*
    | The first thing to ask, once it's connected. Proves the connection and
    | starts the loop. {host} is the host's name.
    */
    'first_prompt' => 'Run a ReviseMy design checkup on the work I just changed: create a review with the right source, share the review link with me, wait for my marks, then follow next_action until I approve.',

];
