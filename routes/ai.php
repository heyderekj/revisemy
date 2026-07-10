<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/revisemy', \App\Mcp\Servers\ReviseMyServer::class)
    ->middleware(['auth:sanctum', 'throttle:120,1']);
