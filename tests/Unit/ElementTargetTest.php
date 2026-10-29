<?php

namespace Tests\Unit;

use App\Support\ElementTarget;
use PHPUnit\Framework\TestCase;

class ElementTargetTest extends TestCase
{
    /** @var array{w: float, h: float} */
    protected array $css = ['w' => 1200.0, 'h' => 2400.0];

    /**
     * A 1200-wide page, 2400 tall at the shot's DPR: a section taller than
     * a screen, a card in it, a button in the card, its label, and a
     * bottom-fixed bar.
     *
     * @return array<string, mixed>
     */
    protected function map(): array
    {
        return [
            'viewport' => ['width' => 1200, 'height' => 800],
            'elements' => [
                ['selector' => 'section', 'kind' => 'Section', 'box' => ['x' => 0, 'y' => 0, 'w' => 1200, 'h' => 1600], 'parent' => null],
                ['selector' => '.card', 'kind' => 'Box', 'box' => ['x' => 100, 'y' => 100, 'w' => 400, 'h' => 300], 'parent' => 0],
                ['selector' => '.card .wrap', 'kind' => 'Box', 'box' => ['x' => 101, 'y' => 101, 'w' => 398, 'h' => 298], 'parent' => 1],
                ['selector' => '#buy', 'tag' => 'a', 'kind' => 'Button', 'text' => 'Buy now', 'box' => ['x' => 150, 'y' => 300, 'w' => 200, 'h' => 60], 'parent' => 2, 'styles' => ['fs' => 18, 'lh' => 24, 'ff' => 'Inter', 'fw' => 600, 'c' => '#ffffff', 'bg' => '#2563eb', 'p' => [18, 32, 18, 32], 'r' => 10, 'b' => [1, '#1d4ed8']]],
                ['selector' => 'nav.bottom', 'kind' => 'Navigation', 'fixed' => 'bottom', 'box' => ['x' => 0, 'y' => 740, 'w' => 1200, 'h' => 60], 'parent' => null],
            ],
        ];
    }

    /** @return array{x: float, y: float, w: float, h: float} */
    protected function share(int $x, int $y, int $w, int $h): array
    {
        return ['x' => $x / 1200, 'y' => $y / 2400, 'w' => $w / 1200, 'h' => $h / 2400];
    }

    public function test_a_pick_is_kept_when_the_click_is_on_it(): void
    {
        $target = ElementTarget::fromMap($this->map(), $this->css, 250 / 1200, 330 / 2400, null, 3);

        $this->assertSame(['#buy', 'picked', 3], [$target['selector'], $target['via'], $target['index']]);
        $this->assertSame('#buy', ElementTarget::fromMap($this->map(), $this->css, 250 / 1200, 330 / 2400, null, '#buy')['selector']);
    }

    public function test_a_pick_far_from_the_click_falls_back_to_matching(): void
    {
        $this->assertNull(ElementTarget::fromMap($this->map(), $this->css, 0.9, 0.9, null, 3), 'a point in the tall section matches nothing');
        $this->assertSame(['.card .wrap', 'point'], array_values(array_intersect_key(
            ElementTarget::fromMap($this->map(), $this->css, 0.1, 0.1, null, 99),
            ['selector' => 1, 'via' => 1],
        )), 'an unknown pick is matched like a point');
    }

    public function test_a_drawn_box_matches_only_what_it_closely_covers(): void
    {
        $close = ElementTarget::fromMap($this->map(), $this->css, 0, 0, $this->share(140, 290, 220, 80));
        $this->assertSame(['#buy', 'matched'], [$close['selector'], $close['via']]);

        $this->assertNull(ElementTarget::fromMap($this->map(), $this->css, 0, 0, $this->share(140, 290, 600, 400)));
    }

    public function test_the_closest_match_wins(): void
    {
        // The card and its wrapper both cover the box; the card covers it exactly.
        $this->assertSame('.card', ElementTarget::fromMap($this->map(), $this->css, 0, 0, $this->share(100, 100, 400, 300))['selector']);
        $this->assertSame('.card .wrap', ElementTarget::fromMap($this->map(), $this->css, 0, 0, $this->share(101, 101, 398, 298))['selector']);
    }

    public function test_a_point_takes_the_smallest_element_under_it_no_bigger_than_a_screen(): void
    {
        $this->assertSame(['#buy', 'point'], array_values(array_intersect_key(
            ElementTarget::fromMap($this->map(), $this->css, 250 / 1200, 330 / 2400, null),
            ['selector' => 1, 'via' => 1],
        )));
        $this->assertSame('.card .wrap', ElementTarget::fromMap($this->map(), $this->css, 120 / 1200, 120 / 2400, null)['selector']);
    }

    public function test_bottom_fixed_bars_are_never_matched(): void
    {
        $this->assertNull(ElementTarget::fromMap($this->map(), $this->css, 0, 0, $this->share(0, 740, 1200, 60)));
    }

    public function test_wider_skips_wrappers_barely_bigger_than_the_element(): void
    {
        $this->assertSame(2, ElementTarget::parentOf($this->map(), 3));
        $this->assertSame(0, ElementTarget::parentOf($this->map(), 2), 'the card is under 5% bigger than its wrapper');
        $this->assertNull(ElementTarget::parentOf($this->map(), 0));
    }

    public function test_the_agent_and_the_person_read_the_same_element(): void
    {
        $target = ElementTarget::fromMap($this->map(), $this->css, 250 / 1200, 330 / 2400, null, 3);

        $this->assertSame('Button “Buy now”', ElementTarget::summary($target));
        $this->assertSame('Navigation <div>', ElementTarget::summary(['kind' => 'Navigation']));

        $agent = ElementTarget::forAgent($target);
        $this->assertSame(['font_size' => 18, 'line_height' => 24, 'font_weight' => 600, 'font_family' => 'Inter', 'color' => '#ffffff', 'background' => '#2563eb', 'padding' => [18, 32, 18, 32], 'radius' => 10, 'border' => [1, '#1d4ed8']], $agent['styles']);
        $this->assertNull(ElementTarget::forAgent(null));

        $rows = collect(ElementTarget::inspectRows($target))->mapWithKeys(fn ($row) => [$row['label'] => $row['value']])->all();
        $this->assertSame([
            'Size' => '200 × 60',
            'Type' => 'Inter · 18px/24px · 600',
            'Colour' => '#ffffff',
            'Fill' => '#2563eb',
            'Padding' => '18px 32px',
            'Corners' => '10px',
            'Border' => '1px #1d4ed8',
        ], $rows);
    }

    public function test_a_picked_element_becomes_the_mark_area(): void
    {
        $this->assertEqualsWithDelta(
            ['x' => 0.125, 'y' => 0.125, 'w' => 1 / 6, 'h' => 0.025],
            ElementTarget::area(['box' => ['x' => 150, 'y' => 300, 'w' => 200, 'h' => 60]], $this->css),
            0.0001,
        );
    }
}
