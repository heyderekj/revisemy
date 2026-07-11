<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DocumentIngestionService
{
    public const MAX_PAGES = 5;

    /**
     * Render a PDF (base64 string or https URL) into one PNG per page,
     * capped at the review's screenshot limit.
     *
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function pdfToImages(string $pdf): array
    {
        if (! extension_loaded('imagick')) {
            throw ValidationException::withMessages([
                'pdf' => 'PDF ingestion needs the Imagick PHP extension on this server — upload per-page screenshots instead.',
            ]);
        }

        $binary = $this->resolvePdfBinary($pdf);

        try {
            $probe = new \Imagick;
            $probe->pingImageBlob($binary);
            $pages = min($probe->getNumberImages(), self::MAX_PAGES);
            $probe->clear();

            $shots = [];

            for ($page = 0; $page < $pages; $page++) {
                $imagick = new \Imagick;
                $imagick->setResolution(150, 150);
                $imagick->readImageBlob($binary."[{$page}]");
                $imagick->setImageBackgroundColor('white');
                $imagick = $imagick->flattenImages();
                $imagick->setImageFormat('png');

                $shots[] = [
                    'binary' => $imagick->getImageBlob(),
                    'meta' => ['origin' => 'pdf', 'page' => $page + 1],
                ];

                $imagick->clear();
            }

            return $shots;
        } catch (\ImagickException $e) {
            throw ValidationException::withMessages([
                'pdf' => 'Could not render that PDF: '.$e->getMessage(),
            ]);
        }
    }

    protected function resolvePdfBinary(string $pdf): string
    {
        $pdf = trim($pdf);

        if (filter_var($pdf, FILTER_VALIDATE_URL)) {
            $response = Http::timeout(20)->get($pdf);

            if (! $response->successful()) {
                throw ValidationException::withMessages([
                    'pdf' => 'Could not download that PDF URL.',
                ]);
            }

            $binary = $response->body();
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

        if (strlen($binary) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'pdf' => 'Keep the PDF under 20MB.',
            ]);
        }

        return $binary;
    }
}
