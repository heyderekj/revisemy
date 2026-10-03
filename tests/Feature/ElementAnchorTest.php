<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\AddMarkTool;
use App\Mcp\Tools\GetElementsTool;
use App\Mcp\Tools\GetReviewTool;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\User;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use App\Support\ElementAnchor;
use App\Support\PinStack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElementAnchorTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngBinary(): string
    {
        return hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        );
    }

    /**
     * @param  array<string, string>  $texts  selector => text
     * @return array{binary: string, meta: array<string, mixed>, elements: array<string, mixed>}
     */
    protected function capture(array $texts): array
    {
        $elements = [];
        $y = 100;

        foreach ($texts as $selector => $text) {
            $elements[] = ['selector' => $selector, 'tag' => 'a', 'kind' => 'Button', 'text' => $text, 'src' => null, 'box' => ['x' => 100, 'y' => $y, 'w' => 200, 'h' => 50]];
            $y += 200;
        }

        return [
            'binary' => $this->tinyPngBinary(),
            'meta' => ['origin' => 'capture', 'viewport' => 'desktop-1280', 'css_width' => 1280, 'dpr' => 1],
            'elements' => ['docWidth' => 1000, 'docHeight' => 2000, 'elements' => $elements],
        ];
    }

    /**
     * @return array{0: User, 1: Review}
     */
    protected function setUpCapturedReview(array $texts = ['#cta' => 'Sign up', '#promo' => 'Spring sale']): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $try = app(TryTokenService::class)->create();

        $review = app(ReviewService::class)->create($try['workspace'], 'Landing', null, [$this->capture($texts)]);

        return [$try['user'], $review];
    }

    public function test_capture_element_maps_normalize_to_the_capture(): void
    {
        [, $review] = $this->setUpCapturedReview();
        $shot = $review->screenshots->first();

        $this->assertSame(2, $shot->meta['element_count']);

        $resolved = ElementAnchor::resolve($shot, '#cta');
        $this->assertSame('Button', $resolved['kind']);
        $this->assertSame('Sign up', $resolved['text']);
        $this->assertEqualsWithDelta(0.1, $resolved['area']['x'], 0.0001);
        $this->assertEqualsWithDelta(0.05, $resolved['area']['y'], 0.0001);
        $this->assertEqualsWithDelta(0.2, $resolved['area']['w'], 0.0001);
        $this->assertEqualsWithDelta(0.025, $resolved['area']['h'], 0.0001);

        $this->assertNull(ElementAnchor::resolve($shot, '#nope'));

        $canvas = ElementAnchor::forCanvas($shot);
        $this->assertSame(['s' => '#cta', 'k' => 'Button', 't' => 'Sign up', 'b' => [0.1, 0.05, 0.2, 0.025]], $canvas[0]);

        // Served signed for the review canvas.
        $this->get($shot->elementsUrl())->assertOk()->assertJsonPath('elements.1.s', '#promo');
        $this->get(route('screenshots.elements', $shot))->assertForbidden();
    }

    public function test_marks_store_the_element_from_the_capture_not_the_browser(): void
    {
        [$user, $review] = $this->setUpCapturedReview();
        $shot = $review->screenshots->first();

        ReviseMyServer::actingAs($user)->tool(AddMarkTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
            'x' => 0.2,
            'y' => 0.0625,
            'area' => ['x' => 0.1, 'y' => 0.05, 'w' => 0.2, 'h' => 0.025],
            'severity' => 'must-fix',
            'body' => 'Say what happens next.',
            'selector' => '#cta',
        ])->assertHasNoErrors();

        $mark = Annotation::query()->firstOrFail();
        $this->assertSame(['selector' => '#cta', 'tag' => 'a', 'kind' => 'Button', 'text' => 'Sign up'], $mark->element);
        $this->assertSame('Button “Sign up”', $mark->elementLabel());

        // An unknown selector leaves the mark unanchored.
        app(MarkLifecycleService::class)->createMark($shot, 0.5, 0.5, null, 'nit', 'Somewhere', ['selector' => 'div > p']);
        $this->assertNull(Annotation::query()->where('number', 2)->firstOrFail()->element);

        // Agents read the exact target.
        ReviseMyServer::actingAs($user)->tool(GetReviewTool::class, ['id' => $review->public_id])
            ->assertStructuredContent(fn ($json) => $json
                ->where('screenshots.0.pins.0.element.selector', '#cta')
                ->where('screenshots.0.pins.0.element.text', 'Sign up')
                ->etc());
    }

    public function test_get_elements_serves_the_inline_app(): void
    {
        [$user, $review] = $this->setUpCapturedReview();
        $shot = $review->screenshots->first();

        $meta = app(GetElementsTool::class)->toArray()['_meta'] ?? [];
        $this->assertSame(['app'], $meta['ui']['visibility'] ?? null);

        ReviseMyServer::actingAs($user)->tool(GetElementsTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
        ])->assertHasNoErrors()->assertStructuredContent(fn ($json) => $json
            ->where('screenshot_id', $shot->id)
            ->has('elements', 2)
            ->where('elements.0.s', '#cta'));
    }

    public function test_follow_up_capture_carries_marks_forward(): void
    {
        [, $review] = $this->setUpCapturedReview();
        $shot = $review->screenshots->first();
        $lifecycle = app(MarkLifecycleService::class);

        $copy = $lifecycle->createMark($shot, 0.2, 0.06, null, 'must-fix', 'Say what happens next.', [
            'selector' => '#cta',
            'suggested_copy' => 'Start  free',
        ]);
        $promo = $lifecycle->createMark($shot, 0.2, 0.16, null, 'must-fix', 'Drop the sale.', ['selector' => '#promo']);
        $loose = $lifecycle->createMark($shot, 0.9, 0.9, null, 'nit', 'Not anchored.');

        app(ReviewService::class)->create(
            $review->workspace,
            'Landing',
            null,
            [$this->capture(['#hero' => 'Welcome', '#cta' => 'start free today'])],
            parentPublicId: $review->public_id,
        );

        $copy->refresh();
        $this->assertTrue($copy->looksLive());
        $this->assertFalse($copy->missingInNextPass());
        // Boxes move with the page: #cta is now the second element.
        $this->assertEqualsWithDelta(0.15, $copy->carried['area']['y'], 0.0001);

        $this->assertTrue($promo->refresh()->missingInNextPass());
        $this->assertFalse($promo->looksLive());
        $this->assertNull($loose->refresh()->carried);
    }

    public function test_copy_matching_ignores_case_and_whitespace(): void
    {
        $this->assertTrue(ElementAnchor::showsCopy("Start\n free today", 'start free'));
        $this->assertFalse(ElementAnchor::showsCopy('Sign up', 'Start free'));
        $this->assertFalse(ElementAnchor::showsCopy('Sign up', '  '));
    }

    public function test_points_on_the_same_spot_fan_out(): void
    {
        $marks = collect([
            new Annotation(['x' => 0.5, 'y' => 0.5]),
            new Annotation(['x' => 0.505, 'y' => 0.502]),
            new Annotation(['x' => 0.8, 'y' => 0.2]),
        ])->each(fn (Annotation $mark, int $i) => $mark->id = $i + 1);

        $this->assertSame([1 => 0, 2 => 1, 3 => 0], PinStack::offsets($marks));
    }
}
