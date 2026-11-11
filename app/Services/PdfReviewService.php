<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Screenshot;
use App\Support\OutboundUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class PdfReviewService
{
    public const MAX_PAGES = 150;

    public const MAX_PDF_BYTES = 20 * 1024 * 1024;

    public function inspect(string $pdf): array
    {
        $binary = $this->resolvePdfBinary($pdf);
        try {
            $document = json_decode($this->run($binary, ['inspect', self::MAX_PAGES]), true, flags: JSON_THROW_ON_ERROR);
        } catch (\RuntimeException|\JsonException $e) {
            report($e);

            throw ValidationException::withMessages(['pdf' => 'Could not open that PDF. Check that the file is readable and does not require a password.']);
        }
        if (($document['count'] ?? 0) > self::MAX_PAGES || empty($document['pages'])) {
            throw ValidationException::withMessages(['pdf' => 'Upload a PDF with 1 to '.self::MAX_PAGES.' pages.']);
        }

        return ['binary' => $binary, 'pages' => $document['pages']];
    }

    public function store(Review $review, array $document): void
    {
        $disk = ScreenshotStorage::diskName();
        $folder = 'reviews/'.$review->public_id.'/'.Str::random(48);
        $path = $folder.'/original.pdf';
        $storage = Storage::disk($disk);
        if (! $storage->put($path, $document['binary'], ['visibility' => 'private'])) {
            throw new \RuntimeException('Could not store the original PDF.');
        }

        try {
            $review->update(['pdf_path' => $path, 'pdf_disk' => $disk]);
            foreach ($document['pages'] as $index => $page) {
                $pageWidth = max(1, (float) $page['width']);
                $pageHeight = max(1, (float) $page['height']);
                $scale = min(192 / 72, sqrt(3_990_000 / ($pageWidth * $pageHeight)), 4000 / max($pageWidth, $pageHeight));
                $number = $index + 1;
                $review->screenshots()->create([
                    'disk' => $disk,
                    'path' => "{$folder}/page-{$number}.png",
                    'thumb_path' => "{$folder}/page-{$number}.thumb.png",
                    'width' => max(1, (int) round($pageWidth * $scale)),
                    'height' => max(1, (int) round($pageHeight * $scale)),
                    'sort_order' => $index,
                    'kind' => Screenshot::KIND_SOURCE,
                    'meta' => [
                        'origin' => 'pdf', 'page' => $number, 'total_pages' => count($document['pages']),
                        'dpr' => $scale * 72 / 96, 'pdf_lazy' => true, 'pdf_text' => $page['text'],
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            $storage->delete($path);
            throw $e;
        }
    }

    public function ensurePageImage(Screenshot $shot, ?string $path = null): void
    {
        if (! $shot->isPdfPage()) {
            return;
        }

        $path ??= $shot->path;
        $disk = Storage::disk($shot->disk);
        if ($disk->exists($path)) {
            return;
        }

        Cache::lock('pdf-page:'.$shot->id.':'.md5($path), 45)->block(40, function () use ($shot, $path, $disk): void {
            if ($disk->exists($path)) {
                return;
            }
            $scale = $path === $shot->thumb_path ? min(1, 320 / max($shot->width, $shot->height)) : 1;
            $binary = $this->run($this->original($shot->review), [
                'render', $shot->meta['page'], max(1, (int) round($shot->width * $scale)), max(1, (int) round($shot->height * $scale)),
            ]);
            if (! str_starts_with($binary, "\x89PNG\r\n\x1a\n") || ! $disk->put($path, $binary, ['visibility' => 'private'])) {
                throw new \RuntimeException('Could not render this PDF page.');
            }
        });
    }

    public function original(Review $review): string
    {
        $binary = $review->pdf_path ? Storage::disk($review->pdf_disk)->get($review->pdf_path) : null;
        abort_unless(is_string($binary), 404);

        return $binary;
    }

    public function export(Review $review): string
    {
        $marks = $review->screenshots()->with('annotations')->get()->flatMap(fn (Screenshot $shot) => $shot->annotations->map(
            fn ($mark) => [
                'page' => $shot->meta['page'],
                'x' => (float) $mark->x, 'y' => (float) $mark->y, 'area' => $mark->region(),
                'anchor' => $mark->pdf_anchor, 'number' => $mark->number, 'body' => $mark->body,
                'replacement' => $mark->suggested_copy,
            ],
        ));

        $file = $this->temporary(json_encode($marks->values(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        try {
            return $this->run($this->original($review), ['export', stream_get_meta_data($file)['uri']]);
        } finally {
            fclose($file);
        }
    }

    protected function run(string $binary, array $arguments): string
    {
        $file = $this->temporary($binary);
        try {
            $process = new Process([
                'node', '--max-old-space-size=256', __DIR__.'/pdf-review.mjs', $arguments[0],
                stream_get_meta_data($file)['uri'], ...array_slice($arguments, 1),
            ], base_path(), timeout: 35);

            return $process->mustRun()->getOutput();
        } finally {
            fclose($file);
        }
    }

    protected function temporary(string $binary)
    {
        $file = tmpfile();
        if ($file === false) {
            throw new \RuntimeException('Could not create a temporary PDF file.');
        }
        if (fwrite($file, $binary) !== strlen($binary)) {
            fclose($file);
            throw new \RuntimeException('Could not write a temporary PDF file.');
        }

        return $file;
    }

    protected function resolvePdfBinary(string $pdf): string
    {
        $pdf = trim($pdf);

        if (filter_var($pdf, FILTER_VALIDATE_URL)) {
            // Same guard as screenshot URLs — a try token is effectively
            // unauthenticated, so this must not reach the private network.
            $binary = OutboundUrl::fetch($pdf, self::MAX_PDF_BYTES);

            if ($binary === null) {
                throw ValidationException::withMessages([
                    'pdf' => 'Could not download that PDF URL — it must be a public https URL under 20MB.',
                ]);
            }
        } else {
            if (str_starts_with($pdf, 'data:application/pdf;base64,')) {
                $pdf = substr($pdf, strpos($pdf, ',') + 1);
            }

            $binary = base64_decode($pdf, true);

            if ($binary === false) {
                throw ValidationException::withMessages([
                    'pdf' => 'Pass the PDF as an https URL or base64 string.',
                ]);
            }
        }

        if (! str_starts_with($binary, '%PDF')) {
            throw ValidationException::withMessages([
                'pdf' => 'That does not look like a PDF.',
            ]);
        }

        if (strlen($binary) > self::MAX_PDF_BYTES) {
            throw ValidationException::withMessages([
                'pdf' => 'Keep the PDF under 20MB.',
            ]);
        }

        return $binary;
    }
}
