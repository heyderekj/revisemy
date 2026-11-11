<?php

namespace App\Services\Capture;

final class CaptureProfile
{
    public static function settings(): array
    {
        return [
            'full_page' => (bool) config('revisemy.capture.url_full_page', true),
            'scroll_page' => CaptureSettleScript::enabled(),
            'hide_consent' => (bool) config('revisemy.capture.hide_consent', true),
            'hide_chat' => (bool) config('revisemy.capture.hide_chat', true),
            'freeze_animations' => (bool) config('revisemy.capture.freeze_animations', true),
            'place_fixed' => (bool) config('revisemy.capture.place_fixed', true),
        ];
    }

    public static function viewport(array $viewport): array
    {
        return $viewport + [
            'user_agent' => CaptureDevice::userAgent($viewport),
            'base_dpr' => max(1, (int) config('revisemy.capture.url_device_scale_factor', 1)),
            'settings' => self::settings(),
        ];
    }

    public static function meta(array $viewport, string $path, ?int $dpr = null): array
    {
        $viewport = self::viewport($viewport);

        return [
            'viewport' => $viewport['label'],
            'css_width' => $viewport['width'],
            'css_height' => $viewport['height'],
            'mobile' => $viewport['mobile'],
            'capture_profile' => $viewport,
            'capture_path' => $path,
        ] + ($dpr === null ? [] : ['dpr' => $dpr]);
    }
}
