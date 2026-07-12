<?php

namespace App\Services\Capture;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Default capture driver: a Browserless-compatible hosted screenshot API.
 * Laravel Cloud app containers ship no Chrome, so rendering happens over a
 * plain HTTPS POST — {url|html, viewport, options} in, image bytes out.
 */
class HostedCaptureDriver implements CaptureDriver
{
    public function enabled(): bool
    {
        return (string) config('revisemy.capture.endpoint') !== '';
    }

    public function captureUrl(string $url, array $viewports): array
    {
        return $this->capture(['url' => $url], $viewports, ['origin' => 'capture', 'page_url' => $url]);
    }

    public function captureHtml(string $html, array $viewports): array
    {
        return $this->capture(['html' => $html], $viewports, ['origin' => 'html']);
    }

    public function captureDom(string $url): ?string
    {
        $endpoint = (string) config('revisemy.capture.content_endpoint');

        if ($endpoint === '') {
            return null;
        }

        $request = Http::timeout((int) config('revisemy.capture.timeout', 30));

        if ($key = config('revisemy.capture.api_key')) {
            $request = $request->withToken((string) $key);
        }

        $response = $request->post($endpoint, ['url' => $url]);

        if (! $response->successful() || $response->body() === '') {
            Log::warning('Hosted DOM capture failed', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * @param  array<string, string>  $source
     * @param  list<array{0: int, 1: int, 2: string}>  $viewports
     * @param  array<string, mixed>  $baseMeta
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    protected function capture(array $source, array $viewports, array $baseMeta): array
    {
        $endpoint = (string) config('revisemy.capture.endpoint');
        $shots = [];

        foreach ($viewports as [$width, $height, $label]) {
            $request = Http::timeout((int) config('revisemy.capture.timeout', 30));

            if ($key = config('revisemy.capture.api_key')) {
                $request = $request->withToken((string) $key);
            }

            $response = $request->post($endpoint, $source + [
                'viewport' => ['width' => $width, 'height' => $height],
                'options' => ['type' => 'png', 'fullPage' => true],
            ]);

            if (! $response->successful() || $response->body() === '') {
                throw ValidationException::withMessages([
                    'capture' => "Could not capture at {$label} ({$response->status()}).",
                ]);
            }

            $shots[] = [
                'binary' => $response->body(),
                'meta' => $baseMeta + ['viewport' => $label],
            ];
        }

        return $shots;
    }
}
