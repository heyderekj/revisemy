<?php

namespace App\Console\Commands;

use App\Services\Capture\BrowsershotCaptureDriver;
use App\Services\Capture\FunctionUnavailable;
use App\Services\Capture\HostedCaptureDriver;
use App\Services\Capture\PageCaptureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Capture a page the way create_review does, through each path on its own,
 * and say what came back: size, format, DPR, whether everything loaded, how
 * many crop slices and elements came with it, how long each viewport and
 * each of its phases took, and the whole capture with the viewports side by
 * side. The images land in a folder to compare.
 *
 * Reviews never show which path a capture took, and a /function that's
 * turned down falls back to /screenshot quietly, so this is how to see why.
 */
class ProbeCapture extends Command
{
    protected $signature = 'revisemy:probe-capture
        {url : The page to capture}
        {--path=both : function, screenshot or both (hosted driver only)}
        {--out= : Folder for the images (defaults to storage/app/capture-probe)}';

    protected $description = 'Capture a page through each path and say what came back';

    public function handle(PageCaptureService $capture): int
    {
        $url = (string) $this->argument('url');
        $driver = (string) config('revisemy.capture.driver');

        if (! $capture->enabled()) {
            $this->error('Capture is off. Set REVISEMY_CAPTURE_DRIVER (hosted or browsershot) and its endpoint first.');

            return self::FAILURE;
        }

        $paths = match (true) {
            $driver !== 'hosted' => [$driver],
            $this->option('path') === 'both' => ['function', 'screenshot'],
            default => [(string) $this->option('path')],
        };

        $out = (string) ($this->option('out') ?: storage_path('app/capture-probe'));
        File::ensureDirectoryExists($out);
        $prefix = Str::slug((string) parse_url($url, PHP_URL_HOST));

        $rows = [];
        $phases = [];
        $totals = [];
        $failed = false;

        foreach ($paths as $path) {
            // Every viewport in one go, the way create_review shoots them,
            // so the total is the real wall-clock with them side by side.
            $started = microtime(true);

            try {
                $shots = $this->shoot($path, $url, $capture->viewports());
            } catch (FunctionUnavailable $e) {
                $rows[] = [$path, 'all', 'turned down', '', '', '', '', $this->seconds($started), $e->getMessage()];
                $failed = true;

                continue;
            } catch (ValidationException $e) {
                $rows[] = [$path, 'all', 'failed', '', '', '', '', $this->seconds($started), collect($e->errors())->flatten()->first()];
                $failed = true;

                continue;
            }

            $totals[] = "{$path}: ".count($shots).' viewports in '.$this->seconds($started);

            foreach ($shots as $shot) {
                $meta = $shot['meta'];
                $label = (string) ($meta['viewport'] ?? 'shot');
                $info = @getimagesizefromstring($shot['binary']) ?: [0, 0, 'mime' => 'image/png'];
                [$width, $height] = $info;
                $format = (string) Str::after((string) ($info['mime'] ?? 'image/png'), 'image/');
                $base = "{$out}/{$prefix}-{$path}-{$label}";
                File::put("{$base}.{$format}", $shot['binary']);

                if (is_string($shot['thumb'] ?? null)) {
                    File::put("{$base}.thumb.jpg", $shot['thumb']);
                }

                if (is_string($shot['vision']['binary'] ?? null)) {
                    File::put("{$base}.vision.jpg", $shot['vision']['binary']);
                }

                foreach ($shot['slices'] ?? [] as $index => $slice) {
                    File::put("{$base}.slice-{$index}.jpg", $slice['binary']);
                }

                $elements = is_array($shot['elements'] ?? null) ? $shot['elements'] : null;

                if (is_array($meta['timings'] ?? null) && $meta['timings'] !== []) {
                    $phases[] = "{$path} {$label}: ".collect($meta['timings'])
                        ->map(fn ($ms, $phase) => $phase.' '.round($ms / 1000, 1).'s')
                        ->implode(', ');
                }

                $rows[] = [
                    $path,
                    $label,
                    "{$width}×{$height}",
                    round(strlen($shot['binary']) / 1_048_576, 1).'MB',
                    (string) ($meta['dpr'] ?? ''),
                    $this->flag($meta['loaded'] ?? null, (int) ($meta['incomplete_images'] ?? 0)),
                    $this->flag($meta['settled'] ?? null),
                    isset($meta['capture_ms']) ? round($meta['capture_ms'] / 1000, 1).'s' : '—',
                    implode(', ', array_filter([
                        $format,
                        isset($meta['doc_height']) ? "page {$meta['doc_width']}×{$meta['doc_height']} CSS px" : null,
                        isset($shot['thumb']) ? 'thumb from browser' : null,
                        isset($shot['vision']) ? 'vision copy covers '.round($shot['vision']['crop'] * 100).'%' : null,
                        ! empty($shot['slices']) ? count($shot['slices']).' crop slices' : null,
                        $elements !== null ? count((array) ($elements['elements'] ?? [])).' elements (map v'.($elements['version'] ?? 1).')' : null,
                    ])),
                ];
            }
        }

        $this->table(['Path', 'Viewport', 'Image', 'Size', 'DPR', 'Loaded', 'Still', 'Time', 'Note'], $rows);

        foreach ($totals as $line) {
            $this->line($line.' ('.config('revisemy.capture.concurrency').' side by side)');
        }

        foreach ($phases as $line) {
            $this->line($line);
        }

        $this->line('Peak PHP memory: '.round(memory_get_peak_usage(true) / 1_048_576).'MB');
        $this->line("Images are in {$out}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device: string}>  $viewports
     * @return list<array{binary: string, meta: array<string, mixed>, elements?: array<string, mixed>|null, thumb?: string|null, vision?: array{binary: string, crop: float}|null, slices?: list<array{binary: string, top: float, height: float}>}>
     */
    private function shoot(string $path, string $url, array $viewports): array
    {
        return match ($path) {
            'function', 'screenshot' => app(HostedCaptureDriver::class)->captureUrlVia($path, $url, $viewports),
            'browsershot' => app(BrowsershotCaptureDriver::class)->captureUrl($url, $viewports),
            default => throw ValidationException::withMessages(['path' => "Unknown path {$path}."]),
        };
    }

    private function flag(?bool $value, int $missing = 0): string
    {
        return match ($value) {
            null => '—',
            true => 'yes',
            false => $missing > 0 ? "no ({$missing} images)" : 'no',
        };
    }

    private function seconds(float $started): string
    {
        return round(microtime(true) - $started, 1).'s';
    }
}
