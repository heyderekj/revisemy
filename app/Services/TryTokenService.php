<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TryTokenService
{
    /** Align try-token lifetime with default review expiry. */
    public const TOKEN_DAYS = 7;

    /**
     * @return array{
     *     workspace: Workspace,
     *     user: User,
     *     token: string,
     *     token_expires_at: string,
     *     mcp_url: string,
     *     cursor_config: array<string, mixed>,
     *     copilot_config: array<string, mixed>,
     *     claude_desktop_config: array<string, mixed>,
     *     claude_code_command: string,
     *     chatgpt_hint: string,
     *     setup_prompts: array<string, string>,
     *     checkup_prompts: array<string, string>
     * }
     */
    public function create(): array
    {
        return DB::transaction(function (): array {
            $workspace = Workspace::query()->create([
                'name' => 'Try workspace',
            ]);

            $user = User::query()->create([
                'workspace_id' => $workspace->id,
                'name' => 'ReviseMy try user',
                'email' => 'try-'.Str::lower((string) Str::ulid()).'@revisemy.local',
                'password' => Str::password(32),
            ]);

            $expiresAt = now()->addDays(self::TOKEN_DAYS);
            $plainTextToken = $user->createToken('revisemy-try', ['*'], $expiresAt)->plainTextToken;
            $mcpUrl = url('/mcp/revisemy');
            $authHeader = 'Bearer '.$plainTextToken;

            $cursorConfig = [
                'mcpServers' => [
                    'revisemy' => [
                        'url' => $mcpUrl,
                        'headers' => [
                            'Authorization' => $authHeader,
                        ],
                    ],
                ],
            ];

            // Claude Desktop's Connectors UI is OAuth-oriented and won't take a
            // Bearer header. Bridge the remote HTTP server via mcp-remote + Edit Config.
            $claudeDesktopConfig = [
                'mcpServers' => [
                    'revisemy' => [
                        'command' => 'npx',
                        'args' => [
                            '-y',
                            'mcp-remote',
                            $mcpUrl,
                            '--header',
                            'Authorization:${AUTH_HEADER}',
                        ],
                        'env' => [
                            'AUTH_HEADER' => $authHeader,
                        ],
                    ],
                ],
            ];

            $copilotConfig = [
                'servers' => [
                    'revisemy' => [
                        'type' => 'http',
                        'url' => $mcpUrl,
                        'headers' => [
                            'Authorization' => $authHeader,
                        ],
                    ],
                ],
            ];

            $claudeCodeCommand = sprintf(
                'claude mcp add --transport http revisemy %s --header "Authorization: %s"',
                $mcpUrl,
                $authHeader
            );

            return [
                'workspace' => $workspace,
                'user' => $user,
                'token' => $plainTextToken,
                'token_expires_at' => $expiresAt->toIso8601String(),
                'mcp_url' => $mcpUrl,
                'cursor_config' => $cursorConfig,
                'claude_desktop_config' => $claudeDesktopConfig,
                'copilot_config' => $copilotConfig,
                'claude_code_command' => $claudeCodeCommand,
                'chatgpt_hint' => 'Add a remote MCP connector with this URL and Authorization: Bearer <token> (ChatGPT, Grok custom connector, etc.). Or call the REST API at /api/reviews with the same Bearer token.',
                'setup_prompts' => $this->setupPrompts(
                    mcpUrl: $mcpUrl,
                    authHeader: $authHeader,
                    cursorConfig: $cursorConfig,
                    claudeDesktopConfig: $claudeDesktopConfig,
                    copilotConfig: $copilotConfig,
                    claudeCodeCommand: $claudeCodeCommand,
                ),
                'checkup_prompts' => self::checkupPrompts(),
            ];
        });
    }

    /**
     * Prompts an agent can follow to wire ReviseMy MCP into the host.
     *
     * @param  array<string, mixed>  $cursorConfig
     * @param  array<string, mixed>  $claudeDesktopConfig
     * @param  array<string, mixed>  $copilotConfig
     * @return array<string, string>
     */
    public function setupPrompts(
        string $mcpUrl,
        string $authHeader,
        array $cursorConfig,
        array $claudeDesktopConfig,
        array $copilotConfig,
        string $claudeCodeCommand,
    ): array {
        $cursorJson = $this->prettyJson($cursorConfig);
        $claudeDesktopJson = $this->prettyJson($claudeDesktopConfig);
        $copilotJson = $this->prettyJson($copilotConfig);

        return [
            'chatgpt' => <<<PROMPT
Help me connect ReviseMy as a remote MCP connector in ChatGPT (or as a Custom GPT Action if MCP connectors aren’t available on my plan).

Use these exact values:
- Name: revisemy
- MCP URL: {$mcpUrl}
- Authorization header: {$authHeader}

Walk me through Settings → Connectors (or Custom GPT → Actions for REST). After it’s connected, confirm you can see ReviseMy tools like create_review. Then stop — I’ll ask for a design checkup next.
PROMPT,
            'claude_desktop' => <<<PROMPT
Set up the ReviseMy MCP server for Claude Desktop. Do this carefully:

1. Open (or create) the Claude Desktop config file:
   - macOS: ~/Library/Application Support/Claude/claude_desktop_config.json
   - Windows: %APPDATA%\\Claude\\claude_desktop_config.json
2. Merge the JSON below into the top-level "mcpServers" object. Preserve any existing servers.
3. Tell me to fully quit and reopen Claude Desktop (MCP loads at startup only).
4. Do NOT use Connectors → Add custom connector — that UI is OAuth-only and cannot take this Bearer try token.
5. This bridge needs Node.js (npx) available on my machine.

Config to merge:

{$claudeDesktopJson}

When the file is saved correctly, confirm what I should do next (quit/reopen, then verify revisemy tools).
PROMPT,
            'claude_code' => <<<PROMPT
Set up the ReviseMy MCP server for Claude Code.

Run this exact command in the project terminal:

{$claudeCodeCommand}

Then confirm the revisemy MCP tools are available (create_review, get_review, etc.). When setup works, say so briefly — I’ll ask for a design checkup next.
PROMPT,
            'copilot' => <<<PROMPT
Set up the ReviseMy MCP server for GitHub Copilot.

1. Open Copilot → MCP (user or workspace mcp.json).
2. Merge the JSON below under "servers". Preserve any existing servers.
3. Reload Copilot / the window if tools don’t appear.
4. Confirm revisemy tools are available (create_review, get_review, etc.).

Config to merge:

{$copilotJson}

When setup works, say so briefly — I’ll ask for a design checkup next.
PROMPT,
            'cursor' => <<<PROMPT
Set up the ReviseMy MCP server for Cursor.

1. Merge the JSON below into ~/.cursor/mcp.json (create the file if needed). Preserve any existing servers under "mcpServers".
2. Enable "revisemy" in Cursor Settings → MCP if it isn’t already on.
3. Confirm the revisemy tools are available (create_review, get_review, etc.).

Config to merge:

{$cursorJson}

When setup works, say so briefly — I’ll ask for a design checkup next.
PROMPT,
            'grok' => <<<PROMPT
Help me connect ReviseMy as a custom MCP connector on Grok.

Use these exact values:
- Name: revisemy
- MCP URL: {$mcpUrl}
- Authorization header: {$authHeader}

Walk me through https://grok.com/connectors → New Connector → Custom. After it’s connected, confirm you can see ReviseMy tools like create_review. Then stop — I’ll ask for a design checkup next.
PROMPT,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function checkupPrompts(): array
    {
        return [
            'chatgpt' => 'Run a ReviseMy design checkup on the work I just changed. Use create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF), share the review_url if you get one, wait for my marks, then poll get_review and follow next_action until I approve.',
            'claude_desktop' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF). Open the review inline so I can mark and approve, then follow next_action until I’m done. Prefer the design_checkup_loop prompt if available.',
            'claude_code' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source, give me the review_url to mark and approve, then poll get_review and follow next_action until I approve. Prefer the design_checkup_loop prompt if available.',
            'copilot' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots, public URL + capture_url, email HTML, or PDF). Open the review inline so I can mark and approve, then follow next_action until I’m done. Prefer the design_checkup_loop prompt if available.',
            'cursor' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source (screenshots as data URLs for localhost, or public URL + capture_url). Give me the review_url to mark and approve, then poll get_review and follow next_action until I approve. Prefer the design_checkup_loop prompt if available.',
            'grok' => 'Run a ReviseMy design checkup on the work I just changed. Call create_review with the right source, give me the review_url to mark and approve, then poll get_review and follow next_action until I approve.',
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function prettyJson(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
