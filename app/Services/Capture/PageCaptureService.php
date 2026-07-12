<?php

namespace App\Services\Capture;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PageCaptureService
{
    public function enabled(): bool
    {
        return $this->driver()?->enabled() ?? false;
    }

    /**
     * Website capture: one shot per configured viewport (mobile + desktop).
     *
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function captureUrl(string $url): array
    {
        return $this->requireDriver()->captureUrl($url, $this->viewports());
    }

    /**
     * Email/HTML capture: a single ~600px-wide frame, how clients render it.
     *
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function captureHtml(string $html): array
    {
        return $this->requireDriver()->captureHtml($html, [[600, 800, 'email-600']]);
    }

    /**
     * Best-effort rendered-DOM snapshot for AI context; null when the driver
     * is missing, unconfigured for content, or the fetch fails. Never blocks
     * review creation.
     */
    public function captureDom(string $url): ?string
    {
        try {
            return $this->driver()?->captureDom($url);
        } catch (\Throwable $e) {
            Log::warning('DOM capture failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return list<array{0: int, 1: int, 2: string}>
     */
    protected function viewports(): array
    {
        $configured = (array) config('revisemy.capture.viewports', [
            'mobile' => [375, 812],
            'desktop' => [1280, 800],
        ]);

        $viewports = [];
        foreach ($configured as $label => [$width, $height]) {
            $viewports[] = [(int) $width, (int) $height, $label.'-'.$width];
        }

        return $viewports;
    }

    protected function requireDriver(): CaptureDriver
    {
        $driver = $this->driver();

        if (! $driver || ! $driver->enabled()) {
            throw ValidationException::withMessages([
                'capture' => 'Server-side capture is not configured — set REVISEMY_CAPTURE_DRIVER (and endpoint/key for the hosted driver), or upload screenshots directly.',
            ]);
        }

        return $driver;
    }

    protected function driver(): ?CaptureDriver
    {
        return match (config('revisemy.capture.driver')) {
            'hosted' => app(HostedCaptureDriver::class),
            'browsershot' => app(BrowsershotCaptureDriver::class),
            default => null,
        };
    }
}
