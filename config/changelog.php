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
