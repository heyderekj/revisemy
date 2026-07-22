<?php

namespace Tests\Unit;

use App\Support\NormalizedArea;
use PHPUnit\Framework\TestCase;

class NormalizedAreaTest extends TestCase
{
    public function test_canonical_zero_to_one_area(): void
    {
        $area = NormalizedArea::from(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.15]);

        $this->assertSame(['x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.15], $area);
    }

    public function test_width_height_aliases(): void
    {
        $area = NormalizedArea::from(['x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.15]);

        $this->assertNotNull($area);
        $this->assertEqualsWithDelta(0.3, $area['w'], 0.0001);
        $this->assertEqualsWithDelta(0.15, $area['h'], 0.0001);
    }

    public function test_percent_units(): void
    {
        $area = NormalizedArea::from(['x' => 10, 'y' => 20, 'w' => 30, 'h' => 15]);

        $this->assertNotNull($area);
        $this->assertEqualsWithDelta(0.1, $area['x'], 0.0001);
        $this->assertEqualsWithDelta(0.2, $area['y'], 0.0001);
        $this->assertEqualsWithDelta(0.3, $area['w'], 0.0001);
        $this->assertEqualsWithDelta(0.15, $area['h'], 0.0001);
    }

    public function test_tiny_areas_become_null(): void
    {
        $this->assertNull(NormalizedArea::from(['x' => 0.5, 'y' => 0.5, 'w' => 0.001, 'h' => 0.001]));
        $this->assertNull(NormalizedArea::from(null));
        $this->assertNull(NormalizedArea::from('nope'));
    }

    public function test_min_region_size_is_one_percent(): void
    {
        $area = NormalizedArea::from(['x' => 0.5, 'y' => 0.5, 'w' => 0.01, 'h' => 0.01]);

        $this->assertNotNull($area);
        $this->assertEqualsWithDelta(0.01, $area['w'], 0.0001);
        $this->assertEqualsWithDelta(0.01, $area['h'], 0.0001);
    }
}
