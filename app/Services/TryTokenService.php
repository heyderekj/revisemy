<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TryTokenService
{
    /**
     * @return array{
     *     workspace: Workspace,
     *     user: User,
     *     token: string,
     *     mcp_url: string,
     *     cursor_config: array<string, mixed>,
     *     copilot_config: array<string, mixed>,
     *     claude_desktop_config: array<string, mixed>,
     *     claude_code_command: string,
     *     chatgpt_hint: string
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

            $plainTextToken = $user->createToken('revisemy-try', ['*'])->plainTextToken;
            $mcpUrl = url('/mcp/revisemy');
            $authHeader = 'Bearer '.$plainTextToken;

            $cursorStyle = [
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

            return [
                'workspace' => $workspace,
                'user' => $user,
                'token' => $plainTextToken,
                'mcp_url' => $mcpUrl,
                'cursor_config' => $cursorStyle,
                'claude_desktop_config' => $claudeDesktopConfig,
                'copilot_config' => [
                    'servers' => [
                        'revisemy' => [
                            'type' => 'http',
                            'url' => $mcpUrl,
                            'headers' => [
                                'Authorization' => $authHeader,
                            ],
                        ],
                    ],
                ],
                'claude_code_command' => sprintf(
                    'claude mcp add --transport http revisemy %s --header "Authorization: %s"',
                    $mcpUrl,
                    $authHeader
                ),
                'chatgpt_hint' => 'Add a remote MCP connector with this URL and Authorization: Bearer <token> (ChatGPT, Grok custom connector, etc.). Or call the REST API at /api/reviews with the same Bearer token.',
            ];
        });
    }
}
