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
            return Response::error('Sign in with your ReviseMy try token (Bearer Authorization header) before calling tools.');
        }

        return $user->workspace;
    }
}
