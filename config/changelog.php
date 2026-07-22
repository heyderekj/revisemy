<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Release notes (Keep a Changelog style)
    |--------------------------------------------------------------------------
    |
    | Newest first. Bumped by `php artisan revisemy:bump` — fill highlights
    | before you commit. Displayed on /changelog.
    |
    */

    'entries' => [

        [
            'version' => '1.1.0',
            'date' => '2026-07-21',
            'title' => 'Yellow mark and framed marketing',
            'highlights' => [
                'New yellow app mark across logo wordmark, favicons, and Open Graph card',
                'Framed rails chrome shared by homepage and guide / use-case / legal pages',
                'Shared site footer with Testament Made copyright',
                'MCP inline review closer to web: legend, previous pass, agent notes, before/after, decision callouts',
                'O’Saasy license for the open-source product',
            ],
            'links' => [
                ['label' => 'Changelog', 'href' => '/changelog'],
                ['label' => 'Board', 'href' => '/board'],
                ['label' => 'MCP Apps', 'href' => '/mcp-apps'],
            ],
        ],

        [
            'version' => '1.0.0',
            'date' => '2026-07-13',
            'title' => 'Human-in-the-loop design checkup',
            'highlights' => [
                'MCP design checkup loop with create_review, get_review, and next_action',
                'Owner marks, guest suggestions, and board lifecycle open → verified',
                'Second opinion checklist plus optional BYOK vision hints',
                'Connectors for ChatGPT, Claude, Copilot, Cursor, and Grok',
                'Discovery pages for review types, audiences, and alternatives',
            ],
            'links' => [
                ['label' => 'Connectors', 'href' => '/connectors'],
                ['label' => 'Second opinion', 'href' => '/second-opinion'],
                ['label' => 'Board', 'href' => '/board'],
            ],
        ],

    ],

];
