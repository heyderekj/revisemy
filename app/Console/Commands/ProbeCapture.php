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
 * and say what came back: size, DPR, whether everything loaded, and how long
 * each viewport took. The images land in a folder to compare side by side.
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
        $failed = false;

        foreach ($paths as $path) {
            foreach ($capture->viewports() as $viewport) {
                $started = microtime(true);

                try {
                    $shot = $this->shoot($path, $url, $viewport)[0];
                } catch (FunctionUnavailable $e) {
                    $rows[] = [$path, $viewport['label'], 'turned down', '', '', '', '', $this->seconds($started), $e->getMessage()];
                    $failed = true;

                    break;
                } catch (ValidationException $e) {
                    $rows[] = [$path, $viewport['label'], 'failed', '', '', '', '', $this->seconds($started), collect($e->errors())->flatten()->first()];
                    $failed = true;

                    continue;
                }

                $file = "{$out}/{$prefix}-{$path}-{$viewport['label']}.png";
                File::put($file, $shot['binary']);

                if (is_string($shot['thumb'] ?? null)) {
                    File::put(preg_replace('/\.png$/', '.thumb.jpg', $file), $shot['thumb']);
                }

                [$width, $height] = @getimagesizefromstring($shot['binary']) ?: [0, 0];
                $meta = $shot['meta'];

                $rows[] = [
                    $path,
                    $viewport['label'],
                    "{$width}×{$height}",
                    round(strlen($shot['binary']) / 1_048_576, 1).'MB',
                    (string) ($meta['dpr'] ?? ''),
                    $this->flag($meta['loaded'] ?? null, (int) ($meta['incomplete_images'] ?? 0)),
                    $this->flag($meta['settled'] ?? null),
                    $this->seconds($started),
                    isset($shot['thumb']) ? 'thumb from browser' : '',
                ];
            }
        }

        $this->table(['Path', 'Viewport', 'Image', 'Size', 'DPR', 'Loaded', 'Still', 'Time', 'Note'], $rows);
        $this->line('Peak PHP memory: '.round(memory_get_peak_usage(true) / 1_048_576).'MB');
        $this->line("Images are in {$out}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{width: int, height: int, label: string, dpr: int|null, mobile: bool, device: string}  $viewport
     * @return list<array{binary: string, meta: array<string, mixed>, thumb?: string|null}>
     */
    private function shoot(string $path, string $url, array $viewport): array
    {
        return match ($path) {
            'function', 'screenshot' => app(HostedCaptureDriver::class)->captureUrlVia($path, $url, [$viewport]),
            'browsershot' => app(BrowsershotCaptureDriver::class)->captureUrl($url, [$viewport]),
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
