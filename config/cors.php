<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Claude, ChatGPT and Grok call ReviseMy from their servers, where CORS
    | doesn't apply. Browser-based MCP clients (MCP Inspector, web agents)
    | call from a page, so discovery, registration, the token swap and the
    | MCP URL answer any origin. None of them use cookies: the bearer token
    | travels in a header, so credentials stay off. /oauth/authorize is a
    | page the person visits, not a fetch, and keeps the default.
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        '.well-known/oauth-protected-resource',
        '.well-known/oauth-protected-resource/*',
        '.well-known/oauth-authorization-server',
        '.well-known/oauth-authorization-server/*',
        'oauth/register',
        'oauth/token',
        'mcp/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // A browser client has to read these to start sign-in and keep a session.
    'exposed_headers' => ['WWW-Authenticate', 'Mcp-Session-Id', 'Mcp-Protocol-Version'],

    'max_age' => 86400,

    'supports_credentials' => false,

];
