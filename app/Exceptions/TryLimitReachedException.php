<?php

namespace App\Exceptions;

use RuntimeException;

/** An address has made as many try workspaces as TryTokenGate allows for now. */
class TryLimitReachedException extends RuntimeException {}
