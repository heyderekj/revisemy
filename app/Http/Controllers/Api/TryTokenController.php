<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TryTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TryTokenController extends Controller
{
    public function store(Request $request, TryTokenService $tryTokens): JsonResponse
    {
        try {
            $key = 'try-token:'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, 10)) {
                return response()->json([
                    'message' => 'Slow down — try again in a minute.',
                ], 429);
            }

            RateLimiter::hit($key, 60);

            $result = $tryTokens->create();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not create try token. On Laravel Cloud, attach Postgres and run migrations — SQLite does not persist across deploys.',
                // Temporary: remove once Cloud DB is confirmed healthy.
                'error' => $e->getMessage(),
                'type' => $e::class,
            ], 500);
        }

        return response()->json([
            'token' => $result['token'],
            'mcp_url' => $result['mcp_url'],
            'workspace_id' => $result['workspace']->public_id,
            'cursor_config' => $result['cursor_config'],
            'claude_desktop_config' => $result['claude_desktop_config'],
            'copilot_config' => $result['copilot_config'],
            'claude_code_command' => $result['claude_code_command'],
            'chatgpt_hint' => $result['chatgpt_hint'],
            'next_steps' => [
                'Pick your client on the homepage (ChatGPT, Claude, Copilot, Cursor, or Grok) and paste the matching config.',
                'Ask your agent to capture your work and call create_review — or use the design_checkup_loop MCP prompt.',
                'MCP Apps hosts: mark and approve inline in chat. CLI/link hosts: open the review_url link.',
                'Poll get_review and follow next_action until approved or a follow-up pass is needed.',
            ],
        ], 201);
    }
}
