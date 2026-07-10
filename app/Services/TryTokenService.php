<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TryTokenService
{
    /**
     * @return array{workspace: Workspace, user: User, token: string, mcp_url: string, cursor_config: array<string, mixed>}
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

            return [
                'workspace' => $workspace,
                'user' => $user,
                'token' => $plainTextToken,
                'mcp_url' => $mcpUrl,
                'cursor_config' => [
                    'mcpServers' => [
                        'revisemy' => [
                            'url' => $mcpUrl,
                            'headers' => [
                                'Authorization' => 'Bearer '.$plainTextToken,
                            ],
                        ],
                    ],
                ],
            ];
        });
    }
}
