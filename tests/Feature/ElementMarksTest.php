<?php

namespace Tests\Feature;

use App\Mcp\Resources\ElementCollector;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\AddMarkTool;
use App\Mcp\Tools\AddScreenshotTool;
use App\Mcp\Tools\GetShotElementsTool;
use App\Mcp\Tools\LookAtMarksTool;
use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Review;
use App\Models\User;
use App\Services\Capture\CaptureSettleScript;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Marks know the element they're on: picked with a click, matched from a
 * drawn box, kept as a snapshot, and handed to the agent with each pin.
 */
class ElementMarksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The fixture the browser specs use: a 1200×800 shot at 1×, a hero box
     * holding a heading and a #buy button.
     *
     * @return array<string, mixed>
     */
    protected function elements(): array
    {
        return [
            'version' => 2,
            'docWidth' => 1200,
            'docHeight' => 800,
            'viewport' => ['width' => 1200, 'height' => 800],
            'elements' => [
                ['selector' => '#hero', 'tag' => 'section', 'kind' => 'Box', 'text' => 'Plans for every team Buy now', 'box' => ['x' => 60, 'y' => 60, 'w' => 600, 'h' => 300], 'parent' => null, 'styles' => ['bg' => '#eef2ff', 'r' => 16]],
                ['selector' => '#hero > h1', 'tag' => 'h1', 'kind' => 'Heading', 'text' => 'Plans for every team', 'box' => ['x' => 100, 'y' => 100, 'w' => 400, 'h' => 48], 'parent' => 0, 'styles' => ['fs' => 40, 'fw' => 700]],
                ['selector' => '#buy', 'tag' => 'a', 'kind' => 'Button', 'text' => 'Buy now', 'role' => 'link', 'box' => ['x' => 100, 'y' => 200, 'w' => 200, 'h' => 60], 'parent' => 0, 'styles' => ['fs' => 18, 'c' => '#ffffff', 'bg' => '#2563eb', 'p' => [18, 32, 18, 32], 'r' => 10]],
            ],
        ];
    }

    protected function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * @return array{0: User, 1: Review, 2: string}
     */
    protected function setUpReview(bool $withElements = true): array
    {
        Storage::fake('public');
        Queue::fake();
        config(['filesystems.revisemy_disk' => 'public', 'revisemy.second_opinion_enabled' => false]);

        $try = app(TryTokenService::class)->create();
        $response = $this->withToken($try['token'])->postJson('/api/reviews', [
            'title' => 'Pricing',
            'images' => [$this->png(1200, 800)],
        ] + ($withElements ? ['image_details' => [['dpr' => 1, 'elements' => $this->elements()]]] : []))->assertCreated();

        return [$try['user'], Review::query()->where('public_id', $response->json('id'))->firstOrFail(), $try['token']];
    }

    public function test_an_uploaded_map_is_kept_and_its_list_is_served_like_the_shot(): void
    {
        [, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        $this->assertSame(3, $shot->meta['element_count']);
        $this->assertSame('upload', $shot->meta['elements_source']);
        $this->assertSame(1.0, (float) $shot->meta['dpr']);

        $list = $this->get('/r/'.$review->share_token.'/shots/'.$shot->id.'/elements')->assertOk()->json();
        $this->assertSame([2, 100, 200, 200, 60, 0, 'Buy now', 0], $list['e'][2]);
        $this->assertSame(['Box', 'Heading', 'Button'], $list['kinds']);

        $this->get($shot->elementsUrl())->assertOk();
        $this->get('/r/not-the-token/shots/'.$shot->id.'/elements')->assertNotFound();
        $this->get('/shots/'.$shot->id.'/elements')->assertForbidden();
    }

    public function test_a_shot_without_a_map_has_no_element_list(): void
    {
        [, $review] = $this->setUpReview(withElements: false);
        $shot = $review->screenshots()->firstOrFail();

        $this->assertNull($shot->elementsUrl());
        $this->get('/r/'.$review->share_token.'/shots/'.$shot->id.'/elements')->assertNotFound();
    }

    public function test_a_map_that_does_not_fit_the_image_is_left_out_and_the_review_still_opens(): void
    {
        Storage::fake('public');
        Queue::fake();
        $token = app(TryTokenService::class)->create()['token'];

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Mismatch',
            'images' => [$this->png(1200, 800)],
            'image_details' => [['dpr' => 2, 'elements' => $this->elements()]],
        ])->assertCreated();

        $meta = $response->json('screenshots.0.meta');
        $this->assertArrayNotHasKey('element_count', $meta);
        $this->assertStringContainsString("doesn't match", $meta['elements_skipped']);
    }

    public function test_without_a_dpr_the_map_works_it_out(): void
    {
        Storage::fake('public');
        Queue::fake();
        $token = app(TryTokenService::class)->create()['token'];

        $map = $this->elements();
        $map['docWidth'] = 600;

        $response = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Retina',
            'images' => [$this->png(1200, 800)],
            'image_details' => [['elements' => json_encode($map)]],
        ])->assertCreated();

        $this->assertSame(2, $response->json('screenshots.0.meta.dpr'));
    }

    public function test_a_click_picks_the_element_and_the_mark_takes_its_box(): void
    {
        [, $review] = $this->setUpReview();

        $page = Livewire::test('review-page', ['token' => $review->token])
            ->call('startPin', 100 / 1200, 200 / 800, 200 / 1200, 60 / 800, 2)
            ->assertSet('pendingTarget.selector', '#buy')
            ->assertSet('pendingTarget.via', 'picked')
            ->assertSet('pendingParent', 0)
            ->assertSee('Leave a note on this button');

        // Wider: the hero around it.
        $page->call('widenPendingElement')
            ->assertSet('pendingTarget.selector', '#hero')
            ->set('draftBody', 'More air around the hero.')
            ->call('savePin');

        $mark = Annotation::query()->firstOrFail();
        $this->assertSame('#hero', $mark->target['selector']);
        $this->assertEqualsWithDelta(0.05, $mark->region()['x'], 0.0001);
        $this->assertEqualsWithDelta(0.375, $mark->region()['h'], 0.0001);
    }

    public function test_just_the_area_drops_the_element(): void
    {
        [, $review] = $this->setUpReview();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('startPin', 100 / 1200, 200 / 800, 200 / 1200, 60 / 800, 2)
            ->call('dropPendingElement')
            ->set('draftBody', 'Just here.')
            ->call('savePin');

        $this->assertNull(Annotation::query()->firstOrFail()->target);
    }

    public function test_a_drawn_box_is_matched_and_a_spot_stays_a_spot(): void
    {
        [, $review] = $this->setUpReview();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('startPin', 96 / 1200, 196 / 800, 208 / 1200, 68 / 800)
            ->assertSet('pendingTarget.via', 'matched')
            ->set('draftBody', 'Drawn.')
            ->call('savePin')
            ->call('startPin', 300 / 1200, 124 / 800)
            ->assertSet('pendingTarget', null)
            ->set('draftBody', 'Spot.')
            ->call('savePin')
            // Snap off: a drawn box stays free.
            ->call('startPin', 96 / 1200, 196 / 800, 208 / 1200, 68 / 800, null, false)
            ->assertSet('pendingTarget', null);

        $drawn = Annotation::query()->where('body', 'Drawn.')->firstOrFail();
        $this->assertSame('#buy', $drawn->target['selector']);
        // The box stays as drawn.
        $this->assertEqualsWithDelta(96 / 1200, $drawn->region()['x'], 0.0001);
        $this->assertNull(Annotation::query()->where('body', 'Spot.')->firstOrFail()->target);
    }

    public function test_a_small_box_on_a_long_page_is_still_a_box(): void
    {
        [, $review] = $this->setUpReview();

        // 0.5% tall: under the old 1% floor, which turned it into a point.
        Livewire::test('review-page', ['token' => $review->token])
            ->call('startPin', 0.1, 0.1, 0.2, 0.005)
            ->set('draftBody', 'Thin.')
            ->call('savePin');

        $this->assertEqualsWithDelta(0.005, Annotation::query()->firstOrFail()->region()['h'], 0.0001);
    }

    public function test_a_guest_pick_carries_over_when_the_owner_accepts_it(): void
    {
        [, $review] = $this->setUpReview();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startPin', 100 / 1200, 200 / 800, 200 / 1200, 60 / 800, 2)
            ->set('guestName', 'Sam')
            ->set('draftBody', 'Bigger button.')
            ->call('savePin');

        $finding = Finding::query()->firstOrFail();
        $this->assertSame('#buy', $finding->target['selector']);

        Livewire::test('review-page', ['token' => $review->token])->call('acceptFinding', $finding->id);

        $this->assertSame('picked', Annotation::query()->firstOrFail()->target['via']);
    }

    public function test_the_agent_gets_the_element_with_each_pin_and_slimmer_copies_in_the_buckets(): void
    {
        [$user, $review, $token] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        ReviseMyServer::actingAs($user)->tool(AddMarkTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
            'x' => 0.15,
            'y' => 0.29,
            'area' => ['x' => 100 / 1200, 'y' => 200 / 800, 'w' => 200 / 1200, 'h' => 60 / 800],
            'element' => 2,
            'severity' => 'must-fix',
            'body' => 'Say what you get.',
        ])->assertHasNoErrors();

        $payload = $this->withToken($token)->getJson('/api/reviews/'.$review->public_id)->json();
        $pin = $payload['work_packets']['pins'][0];

        $this->assertSame('Button “Buy now”', $pin['element']['summary']);
        $this->assertSame('#buy', $pin['element']['selector']);
        $this->assertSame('#2563eb', $pin['element']['styles']['background']);
        $this->assertSame(['x' => 100, 'y' => 200, 'w' => 200, 'h' => 60], $pin['element']['box']);
        $this->assertSame('picked', $pin['element']['via']);

        $bucketed = $payload['work_packets']['must_fix'][0]['element'];
        $this->assertArrayNotHasKey('styles', $bucketed);
        $this->assertArrayNotHasKey('box', $bucketed);
        $this->assertStringContainsString('element.text is the page', $payload['work_packets']['guidance'] ?? $payload['guidance'] ?? json_encode($payload));

        ReviseMyServer::actingAs($user)->tool(LookAtMarksTool::class, ['id' => $review->public_id])
            ->assertSee('On: Button “Buy now” · `#buy`');
    }

    public function test_add_mark_can_stay_free_of_elements(): void
    {
        [$user, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        ReviseMyServer::actingAs($user)->tool(AddMarkTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
            'x' => 0.2,
            'y' => 0.3,
            'area' => ['x' => 96 / 1200, 'y' => 196 / 800, 'w' => 208 / 1200, 'h' => 68 / 800],
            'snap' => false,
            'severity' => 'nit',
            'body' => 'Free.',
        ])->assertHasNoErrors();

        $this->assertNull(Annotation::query()->firstOrFail()->target);
    }

    public function test_the_inline_review_gets_the_list_and_one_element_through_an_app_only_tool(): void
    {
        [$user, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        $meta = app(GetShotElementsTool::class)->toArray()['_meta'] ?? [];
        $this->assertSame(['app'], $meta['ui']['visibility'] ?? null);

        ReviseMyServer::actingAs($user)->tool(GetShotElementsTool::class, ['review_id' => $review->public_id, 'screenshot_id' => $shot->id])
            ->assertStructuredContent(fn ($json) => $json->where('screenshot_id', $shot->id)->where('elements.kinds', ['Box', 'Heading', 'Button'])->etc());

        ReviseMyServer::actingAs($user)->tool(GetShotElementsTool::class, ['review_id' => $review->public_id, 'screenshot_id' => $shot->id, 'element' => 2])
            ->assertStructuredContent(fn ($json) => $json
                ->where('target.kind', 'Button')
                ->where('target.parent', 0)
                ->where('target.rows.0', ['label' => 'Size', 'value' => '200 × 60'])
                ->etc());

        ReviseMyServer::actingAs($user)->tool(GetShotElementsTool::class, ['review_id' => $review->public_id, 'screenshot_id' => $shot->id, 'area' => ['x' => 96 / 1200, 'y' => 196 / 800, 'w' => 208 / 1200, 'h' => 68 / 800]])
            ->assertStructuredContent(fn ($json) => $json->where('target.kind', 'Button')->etc());

        // Another workspace sees nothing.
        $other = app(TryTokenService::class)->create()['user'];
        ReviseMyServer::actingAs($other)->tool(GetShotElementsTool::class, ['review_id' => $review->public_id, 'screenshot_id' => $shot->id])
            ->assertHasErrors();
    }

    public function test_add_screenshot_takes_a_dpr_and_a_map(): void
    {
        [$user, $review] = $this->setUpReview(withElements: false);

        ReviseMyServer::actingAs($user)->tool(AddScreenshotTool::class, [
            'id' => $review->public_id,
            'image' => $this->png(1200, 800),
            'dpr' => 1,
            'elements' => $this->elements(),
        ])->assertHasNoErrors()->assertSee('Screenshot added');

        $added = $review->screenshots()->where('sort_order', 1)->firstOrFail();
        $this->assertSame(3, $added->meta['element_count']);
    }

    public function test_the_collector_is_one_source_for_captures_agents_and_the_web(): void
    {
        $collector = CaptureSettleScript::collector();

        $this->assertStringContainsString(CaptureSettleScript::collectorSource(), $collector);
        $this->assertStringContainsString(CaptureSettleScript::collectorSource(), CaptureSettleScript::functionBody(collectElements: true));
        $this->get('/capture/elements.js')->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8')->assertContent($collector);
        ReviseMyServer::resource(ElementCollector::class)->assertSee('version: 2');
    }
}
