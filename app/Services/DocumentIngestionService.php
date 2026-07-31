<?php

namespace App\Services;

use App\Support\OutboundUrl;
use Illuminate\Validation\ValidationException;

class DocumentIngestionService
{
    public const MAX_PAGES = 5;

    public const MAX_PDF_BYTES = 20 * 1024 * 1024;

    /**
     * Whether this server can actually rasterise a PDF.
     *
     * The extension being loaded is not enough: Debian and Ubuntu ship an
     * ImageMagick policy.xml that revokes rights on the PDF coder, so Imagick
     * is present but every read fails with an opaque delegate error. Checking
     * the format list covers both the missing extension and the locked-down
     * policy.
     */
    public static function supportsPdf(): bool
    {
        if (! extension_loaded('imagick')) {
            return false;
        }

        return \Imagick::queryFormats('PDF') !== [];
    }

    /**
     * Render a PDF (base64 string or https URL) into one PNG per page,
     * capped at the review's screenshot limit.
     *
     * @return list<array{binary: string, meta: array<string, mixed>}>
     */
    public function pdfToImages(string $pdf): array
    {
        if (! self::supportsPdf()) {
            throw ValidationException::withMessages([
                'pdf' => extension_loaded('imagick')
                    ? 'This server has Imagick but its ImageMagick policy blocks the PDF coder (the Debian/Ubuntu default) — allow PDF in policy.xml, or upload per-page screenshots instead.'
                    : 'PDF ingestion needs the Imagick PHP extension on this server — upload per-page screenshots instead.',
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
            // A blocked PDF coder surfaces as an opaque delegate error ("Unable
            // to ping image blob", "not authorized"). queryFormats() catches
            // most builds up front, but not all of them report the policy the
            // same way — so name the likely cause here too rather than passing
            // ImageMagick's wording straight to the caller.
            throw ValidationException::withMessages([
                'pdf' => self::looksLikeBlockedCoder($e)
                    ? 'This server\'s ImageMagick policy blocks the PDF coder (the Debian/Ubuntu default) — allow PDF in policy.xml, or upload per-page screenshots instead.'
                    : 'Could not render that PDF: '.$e->getMessage(),
            ]);
        }
    }

    protected static function looksLikeBlockedCoder(\ImagickException $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (['not authorized', 'unable to ping', 'no decode delegate', 'nodecodedelegate'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
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
