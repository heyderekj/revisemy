<?php

return [

    'name' => 'ReviseMy',

    'tagline' => 'Visual feedback. With your agent.',

    'description' => 'Human-in-the-loop design review for AI agents. Capture UI, websites, presentations, or email from screenshots, URLs, PDFs, or HTML; mark what matters; track fixes; and send structured next steps back over Laravel MCP.',

    'keywords' => [
        'design review',
        'visual feedback',
        'human in the loop',
        'Laravel MCP',
        'MCP server',
        'AI agents',
        'Cursor',
        'Claude',
        'ChatGPT',
        'UI review',
        'screenshot review',
        'design critique',
        'agent workflow',
    ],

    'author' => 'Derek Castelli',

    'twitter' => '@heyderekj',

    'theme_color' => '#e11d48',

    'github' => 'https://github.com/heyderekj/revisemy',

    'same_as' => [
        'https://github.com/heyderekj/revisemy',
        'https://x.com/heyderekj',
        'https://heyderekj.com/projects/revisemy/',
    ],

    'application_category' => 'DesignApplication',

    'features' => [
        'Rectangle and point marks on screenshots with must-fix, tweak, nit, question, and keep intents',
        'Laravel MCP tools: create_review, get_review, list_reviews, add_screenshot, add_findings, request_second_opinion',
        'Review types for UI, websites, presentations, and email with tailored checklists',
        'Server-side capture from live URLs, PDF decks, and raw HTML',
        'Second opinion hints from a design checklist with optional vision models',
        'Before/after evidence when agents resolve marks',
        'Multi-pass checkups with parent reviews and structured work packets',
        'Try token on the homepage — no account required for reviewers',
    ],

    'mcp_path' => '/mcp/revisemy',

];
