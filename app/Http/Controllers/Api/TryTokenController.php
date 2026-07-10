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
        $key = 'try-token:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json([
                'message' => 'Slow down — try again in a minute.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        $result = $tryTokens->create();

        return response()->json([
            'token' => $result['token'],
            'mcp_url' => $result['mcp_url'],
            'workspace_id' => $result['workspace']->public_id,
            'cursor_config' => $result['cursor_config'],
            'claude_desktop_hint' => 'Add the same URL and Authorization header to your Claude MCP / connector settings.',
            'next_steps' => [
                'Paste the Cursor MCP config into your project settings.',
                'Ask your agent to screenshot UI work and call create_review.',
                'Open the laravel.cloud review link, pin feedback, then approve or request changes.',
            ],
        ], 201);
    }
}
