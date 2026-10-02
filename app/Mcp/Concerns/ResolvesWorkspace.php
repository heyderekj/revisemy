<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait ResolvesWorkspace
{
    protected function workspace(Request $request): Workspace|Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->workspace) {
            return Response::error('Connect ReviseMy first: sign in from your assistant\'s connector settings, or send a try token as a Bearer Authorization header.');
        }

        return $user->workspace;
    }
}
