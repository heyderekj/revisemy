<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Support\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TryTokenService
{
    /**
     * Fallback only — the real lifetime comes from billing.plans.{plan}.token_days
     * (90 on Try, 365 on Plus). This applies just when that config is missing.
     */
    public const TOKEN_DAYS = 14;

    /**
     * @return array{
     *     workspace: Workspace,
     *     user: User,
     *     token: string,
     *     token_expires_at: string,
     *     mcp_url: string,
     *     cursor_config: array<string, mixed>,
     *     copilot_config: array<string, mixed>,
     *     claude_code_command: string,
     *     chatgpt_hint: string,
     *     setup_prompts: array<string, string>,
     *     checkup_prompts: array<string, string>
     * }
     */
    /**
     * A try workspace with this month's credits, and the stand-in user that
     * owns it. No token: the try-token flow mints a Sanctum one, and Connect
     * (OAuth) has Passport mint its own.
     */
    public function createWorkspaceUser(): User
    {
        return DB::transaction(function (): User {
            $workspace = Workspace::query()->create([
                'name' => 'Try workspace',
                'plan' => Workspace::PLAN_FREE,
            ]);

            app(CreditsService::class)->grantPeriod($workspace);

            return User::query()->create([
                'workspace_id' => $workspace->id,
                'name' => 'ReviseMy try user',
                'email' => 'try-'.Str::lower((string) Str::ulid()).'@revisemy.local',
                'password' => Str::password(32),
            ])->setRelation('workspace', $workspace->refresh());
        });
    }

    public function create(): array
    {
        return DB::transaction(function (): array {
            $tokenDays = (int) config('billing.plans.free.token_days', self::TOKEN_DAYS);

            $user = $this->createWorkspaceUser();
            $workspace = $user->workspace;

            $expiresAt = now()->addDays($tokenDays);
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
                'copilot_config' => $copilotConfig,
                'claude_code_command' => $claudeCodeCommand,
                'chatgpt_hint' => 'Claude and ChatGPT need no token: add a custom connector with mcp_url and click Connect when ReviseMy asks. Everything else can send Authorization: Bearer <token>, including the REST API at /api/reviews.',
                'connect_url' => url('/connect'),
                'setup_prompts' => $this->setupPrompts($plainTextToken),
                'checkup_prompts' => self::checkupPrompts(),
            ];
        });
    }

    /**
     * What to tell an agent to set ReviseMy up for itself, one per way in,
     * from the same list the connect hub shows (config/hosts.php `connect`).
     *
     * @return array<string, string>
     */
    public function setupPrompts(string $token): array
    {
        return collect(Hosts::all($token))->map(function (array $host) use ($token) {
            $lines = ["Set up the ReviseMy MCP server in {$host['name']}.", ''];

            foreach ($host['steps'] as $i => $step) {
                $lines[] = ($i + 1).'. '.$step;
            }

            if ($host['command']) {
                $lines[] = '';
                if ($host['needs_token']) {
                    $lines[] = "export REVISEMY_TOKEN={$token}";
                }
                $lines[] = $host['command'];
            } elseif ($host['mode'] !== 'deeplink') {
                $lines[] = '';
                $lines[] = 'Address: '.Hosts::mcpUrl();
            }

            $lines[] = '';
            $lines[] = 'Then confirm the revisemy tools (create_review, get_review) are available, and stop — I’ll ask for a design checkup next.';

            return implode("\n", $lines);
        })->all();
    }

    /**
     * The first thing to ask once connected, per way in.
     *
     * @return array<string, string>
     */
    public static function checkupPrompts(): array
    {
        return collect(config('hosts.connect', []))->map(fn () => Hosts::firstPrompt())->all();
    }
}
