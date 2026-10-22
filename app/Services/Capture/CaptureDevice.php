<?php

namespace App\Services\Capture;

/**
 * How a capture viewport presents itself to the page: its user agent, and
 * the DPR it gets on a path that can't scale the shot to fit the page.
 */
final class CaptureDevice
{
    /**
     * Widest viewport that keeps its own DPR when the page height isn't
     * known up front. Phones stay sharp; wider shots wait for /function.
     */
    public const UNMEASURED_HIDPI_MAX_WIDTH = 480;

    /**
     * Desktop reads as current Chrome on a Mac, not HeadlessChrome, which
     * bot walls turn away; null when desktop_user_agent is empty. Tablets
     * read as an iPad, phones as an iPhone.
     *
     * @param  array{mobile: bool, device?: string}  $viewport
     */
    public static function userAgent(array $viewport): ?string
    {
        if (! $viewport['mobile']) {
            return ((string) config('revisemy.capture.desktop_user_agent')) ?: null;
        }

        $phone = (string) config('revisemy.capture.mobile_user_agent');

        if (($viewport['device'] ?? null) === 'tablet') {
            return (string) (config('revisemy.capture.tablet_user_agent') ?: $phone);
        }

        return $phone;
    }

    /**
     * Most pixels a wide viewport's /screenshot shot may have at its own DPR.
     * The shot can't be scaled to fit, and one over the 16MB cap is shrunk in
     * GD: at 16M that decode stays well inside Cloud's 256MB PHP.
     */
    public const MEASURED_MAX_PIXELS = 16_000_000;

    /**
     * DPR for a full-page URL shot on a path that can't scale the shot to
     * fit, once a load before it has measured the page. A wide viewport gets
     * its own DPR when the page is short enough to stay inside
     * MEASURED_MAX_PIXELS and Chrome's screenshot height, and otherwise
     * stays where unmeasuredDpr() puts it. The page was measured at
     * $measured['width']; a narrower layout only reflows taller, so its
     * height is scaled up by the width ratio.
     *
     * @param  array{width: int, dpr: int|null}  $viewport
     * @param  array{width: int, height: int}|null  $measured
     */
    public static function measuredDpr(array $viewport, ?array $measured): int
    {
        $fallback = self::unmeasuredDpr($viewport);
        $own = $viewport['dpr'] ?? $fallback;

        if ($measured === null || $own <= $fallback || $measured['height'] < 1) {
            return $fallback;
        }

        $height = $measured['height'] * max(1.0, $measured['width'] / max(1, $viewport['width']));
        $fits = ($viewport['width'] * $own) * ($height * $own) <= self::MEASURED_MAX_PIXELS
            && $height * $own <= HostedCaptureDriver::MAX_SHOT_HEIGHT;

        return $fits ? $own : $fallback;
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
