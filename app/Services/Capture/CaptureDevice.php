<?php

namespace App\Services\Capture;

/**
 * How a capture viewport presents itself to the page: its user agent, and
 * the DPR it gets on a path that can't measure the page before the shot.
 */
final class CaptureDevice
{
    /**
     * Widest viewport that keeps its own DPR when the page height isn't
     * known up front. Phones stay sharp; wider shots wait for /function.
     */
    public const UNMEASURED_HIDPI_MAX_WIDTH = 480;

    /**
     * Null for desktop. Tablets read as an iPad, phones as an iPhone.
     *
     * @param  array{mobile: bool, device?: string}  $viewport
     */
    public static function userAgent(array $viewport): ?string
    {
        if (! $viewport['mobile']) {
            return null;
        }

        $phone = (string) config('revisemy.capture.mobile_user_agent');

        if (($viewport['device'] ?? null) === 'tablet') {
            return (string) (config('revisemy.capture.tablet_user_agent') ?: $phone);
        }

        return $phone;
    }

    /**
     * DPR for a full-page URL shot whose height isn't known before it's
     * taken (/screenshot, Browsershot). Phone-width viewports keep their own
     * DPR; wider ones stay at url_device_scale_factor, because a tall 2× PNG
     * there can run Cloud's 256MB PHP out of memory.
     *
     * @param  array{width: int, dpr: int|null}  $viewport
     */
    public static function unmeasuredDpr(array $viewport): int
    {
        $base = max(1, (int) config('revisemy.capture.url_device_scale_factor', 1));

        if ($viewport['width'] > self::UNMEASURED_HIDPI_MAX_WIDTH) {
            return $base;
        }

        return $viewport['dpr'] ?? $base;
    }
}
