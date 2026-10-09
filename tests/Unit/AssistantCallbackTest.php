<?php

namespace Tests\Unit;

use App\Support\AssistantCallback;
use Tests\TestCase;

class AssistantCallbackTest extends TestCase
{
    public function test_known_assistants_are_trusted(): void
    {
        foreach ([
            'https://claude.ai/api/mcp/auth_callback',
            'https://chatgpt.com/connector_platform_oauth_redirect',
            'https://grok.com/connectors/callback',
            'https://vscode.dev/redirect',
            'cursor://anysphere.cursor-mcp/oauth/callback',
            'http://127.0.0.1:43123/callback',
            'http://localhost:6274/oauth/callback',
            'http://[::1]:8080/cb',
        ] as $uri) {
            $this->assertTrue(AssistantCallback::isKnown($uri), $uri);
        }
    }

    public function test_everything_else_is_asked(): void
    {
        foreach ([
            'http://claude.ai/api/mcp/auth_callback',
            'https://evilclaude.ai/cb',
            'https://claude.ai.evil.example/cb',
            'https://user@claude.ai/cb',
            'http://127.0.0.2/cb',
            'javascript:alert(1)',
            'https://evil.example/cb',
            'not a url',
        ] as $uri) {
            $this->assertFalse(AssistantCallback::isKnown($uri), $uri);
        }
    }
}
