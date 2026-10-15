<?php

namespace App\Services\Capture;

use RuntimeException;

/**
 * Browserless turned a /function request down (no endpoint, a plan without
 * it, or a payload or timeout it won't take). The caller falls back to
 * /screenshot; revisemy:probe-capture shows the reason.
 */
class FunctionUnavailable extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $reply)
    {
        parent::__construct('Browserless /function answered HTTP '.$status.($reply !== '' ? ': '.$reply : ''));
    }
}
