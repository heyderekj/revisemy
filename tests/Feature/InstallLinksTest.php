<?php

namespace Tests\Feature;

use App\Support\InstallLinks;
use Tests\TestCase;

class InstallLinksTest extends TestCase
{
    public function test_cursor_and_vscode_links_carry_the_server_and_the_token(): void
    {
        $links = InstallLinks::for('1|secret');

        parse_str((string) parse_url($links['cursor'], PHP_URL_QUERY), $cursor);
        $this->assertSame('revisemy', $cursor['name']);
        $this->assertSame(
            ['url' => url('/mcp/revisemy'), 'headers' => ['Authorization' => 'Bearer 1|secret']],
            json_decode(base64_decode($cursor['config']), true),
        );

        $this->assertStringStartsWith('vscode:mcp/install?', $links['vscode']);
        $vscode = json_decode(rawurldecode(substr($links['vscode'], strlen('vscode:mcp/install?'))), true);
        $this->assertSame('http', $vscode['type']);
        $this->assertSame('Bearer 1|secret', $vscode['headers']['Authorization']);

        $this->assertStringContainsString('--header "Authorization: Bearer 1|secret"', $links['claude_code']);
    }

    public function test_without_a_token_the_editor_signs_in_instead(): void
    {
        $links = InstallLinks::for();

        parse_str((string) parse_url($links['cursor'], PHP_URL_QUERY), $cursor);
        $this->assertSame(['url' => url('/mcp/revisemy')], json_decode(base64_decode($cursor['config']), true));
        $this->assertStringNotContainsString('header', $links['claude_code']);
    }
}
