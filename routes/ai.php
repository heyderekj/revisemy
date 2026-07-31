<?php

use App\Mcp\Servers\ReviseMyServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/revisemy', ReviseMyServer::class)
    ->middleware(['auth:sanctum', 'throttle:120,1']);
