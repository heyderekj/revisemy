<?php

namespace App\Http\Middleware;

use App\Models\OAuthClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remember the last tool an assistant called, per workspace.
 *
 * This is how the connect hub proves a connection on the spot: "Waiting for
 * Claude's first call…" turns into "Claude is connected — it ran
 * list_reviews just now". Only the tool's name and who called it are kept,
 * never what was sent.
 */
class RecordAssistantCall
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user instanceof User && $user->workspace && $response->isSuccessful()
            && $request->json('method') === 'tools/call') {
            $user->workspace->forceFill([
                'assistant_seen_at' => now(),
                'assistant_name' => $this->name($request, $user),
                'assistant_last_tool' => mb_substr((string) $request->json('params.name'), 0, 80),
            ])->saveQuietly();
        }

        return $response;
    }

    /**
     * The OAuth app's own name when it signed in; otherwise a guess from the
     * client's user agent, and "Your assistant" when there's nothing to go on.
     */
    protected function name(Request $request, User $user): string
    {
        $token = $user->currentAccessToken();

        if ($token instanceof AccessToken && filled($token->oauth_client_id)) {
            $name = OAuthClient::query()->whereKey($token->oauth_client_id)->value('name');

            if (filled($name)) {
                return mb_substr((string) $name, 0, 80);
            }
        }

        $agent = strtolower((string) $request->userAgent());

        foreach (['claude-code' => 'Claude Code', 'claude' => 'Claude', 'cursor' => 'Cursor', 'codex' => 'Codex',
            'openai' => 'ChatGPT', 'grok' => 'Grok', 'muse' => 'Muse', 'vscode' => 'VS Code', 'visual studio code' => 'VS Code'] as $needle => $name) {
            if (str_contains($agent, $needle)) {
                return $name;
            }
        }

        return 'Your assistant';
    }
}
