<?php

namespace Tests\Unit;

use App\Models\Screenshot;
use App\Support\MarkFocus;
use PHPUnit\Framework\TestCase;

class MarkFocusTest extends TestCase
{
    public function test_windows_are_always_full_width(): void
    {
        $shot = $this->shot(1280, 12000);

        $point = MarkFocus::windowFromPoint(0.3, 0.2, $shot);
        $this->assertSame(0.0, $point['x']);
        $this->assertSame(1.0, $point['w']);

        $region = MarkFocus::windowFromRegion([
            'x' => 0.2,
            'y' => 0.1,
            'w' => 0.3,
            'h' => 0.05,
        ], $shot);
        $this->assertSame(0.0, $region['x']);
        $this->assertSame(1.0, $region['w']);
    }

    public function test_point_band_on_tall_page_is_a_short_vertical_slice(): void
    {
        $shot = $this->shot(1280, 12000);
        $window = MarkFocus::windowFromPoint(0.5, 0.15, $shot);
        $ratio = MarkFocus::trueAspectRatio($window, $shot);

        $this->assertLessThan(0.12, $window['h']);
        $this->assertGreaterThanOrEqual(MarkFocus::RATIO_MIN, $ratio);
        $this->assertEqualsWithDelta(MarkFocus::TARGET_RATIO, $ratio, 0.45);
    }

    public function test_region_band_covers_padded_region(): void
    {
        $shot = $this->shot(1280, 800);
        $region = ['x' => 0.1, 'y' => 0.4, 'w' => 0.5, 'h' => 0.1];
        $window = MarkFocus::windowFromRegion($region, $shot);

        $this->assertLessThanOrEqual($region['y'] - MarkFocus::REGION_PAD + 0.001, $window['y'] + 0.001);
        $this->assertGreaterThanOrEqual(
            $region['y'] + $region['h'] + MarkFocus::REGION_PAD - 0.001,
            $window['y'] + $window['h'] - 0.001
        );
        $this->assertSame(1.0, $window['w']);
    }

    public function test_band_near_bottom_stays_in_bounds(): void
    {
        $window = MarkFocus::windowFromPoint(0.5, 0.98, $this->shot(1280, 12000));

        $this->assertGreaterThanOrEqual(0.0, $window['y']);
        $this->assertLessThanOrEqual(1.0 + 0.0001, $window['y'] + $window['h']);
        $this->assertSame(1.0, $window['w']);
    }

    public function test_cap_band_height_shortens_extreme_regions(): void
    {
        $shot = $this->shot(1280, 12000);
        $tall = ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 0.5];
        $capped = MarkFocus::capBandHeight($tall, $shot, 0.25);

        $this->assertLessThan($tall['h'], $capped['h']);
        $this->assertSame(1.0, $capped['w']);
        $this->assertGreaterThanOrEqual(MarkFocus::RATIO_MIN - 0.05, MarkFocus::trueAspectRatio($capped, $shot));
    }

    public function test_crop_local_overlay_maps_into_full_width_window(): void
    {
        $window = ['x' => 0.0, 'y' => 0.2, 'w' => 1.0, 'h' => 0.2];
        $region = ['x' => 0.25, 'y' => 0.25, 'w' => 0.5, 'h' => 0.1];

        $local = MarkFocus::toCropLocal($region, $window);

        $this->assertEqualsWithDelta(0.25, $local['x'], 0.0001);
        $this->assertEqualsWithDelta(0.25, $local['y'], 0.0001);
        $this->assertEqualsWithDelta(0.5, $local['w'], 0.0001);
        $this->assertEqualsWithDelta(0.5, $local['h'], 0.0001);
    }

    public function test_background_style_keeps_full_width_scale(): void
    {
        $style = MarkFocus::backgroundStyle('https://example.test/shot.png', [
            'x' => 0.0,
            'y' => 0.25,
            'w' => 1.0,
            'h' => 0.5,
        ]);

        $this->assertStringContainsString('background-size:100.00% 200.00%', $style);
        $this->assertStringContainsString('background-position:0.00% 50.00%', $style);
    }

    protected function shot(int $width, int $height): Screenshot
    {
        $shot = new Screenshot;
        $shot->width = $width;
        $shot->height = $height;

        return $shot;
    }
}
