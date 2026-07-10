<?php

namespace App\Jobs;

use App\Models\Screenshot;
use App\Services\SecondOpinionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateSecondOpinionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public int $screenshotId) {}

    public function handle(SecondOpinionService $opinions): void
    {
        $screenshot = Screenshot::query()->find($this->screenshotId);

        if (! $screenshot) {
            return;
        }

        try {
            $opinions->generate($screenshot);
        } catch (\Throwable $e) {
            Log::warning('GenerateSecondOpinionJob failed', [
                'screenshot_id' => $this->screenshotId,
                'message' => $e->getMessage(),
            ]);
            // Status already set to failed inside generate(); don't retry forever on OpenAI errors.
            if ($this->attempts() >= $this->tries) {
                return;
            }

            throw $e;
        }
    }
}
