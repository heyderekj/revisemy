<?php

namespace Tests\Feature;

use App\Mcp\Resources\ReviewApp;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\WaitForReviewTool;
use App\Models\Annotation;
use App\Models\AnnotationComment;
use App\Models\Review;
use App\Models\User;
use App\Services\MarkLifecycleService;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The loop without polling: the agent waits in one call, sees the marks it
 * is about to fix, and the inline review talks back to the host.
 */
class CloseTheLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 240, 240));
        imagefilledrectangle($image, (int) ($width * 0.2), (int) ($height * 0.1), (int) ($width * 0.6), (int) ($height * 0.15), imagecolorallocate($image, 40, 40, 40));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * @return array{0: User, 1: Review, 2: string}
     */
    protected function setUpReview(string $status = Review::STATUS_PENDING, ?string $image = null): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
            'revisemy.wait_max_seconds' => 1,
            'revisemy.wait_poll_ms' => 100,
            'revisemy.wait_heartbeat_ms' => 300,
        ]);

        $try = app(TryTokenService::class)->create();
        $review = app(ReviewService::class)->create($try['workspace'], 'Pricing page', null, [$image ?? $this->png(800, 600)]);

        if ($status !== Review::STATUS_PENDING) {
            $review->update(['status' => $status, 'decision_at' => now()]);
        }

        return [$try['user'], $review->fresh(), $try['token']];
    }

    protected function mark(Review $review, array $attributes = []): Annotation
    {
        return $review->screenshots()->firstOrFail()->annotations()->create($attributes + [
            'x' => 0.4, 'y' => 0.12, 'area' => ['x' => 0.2, 'y' => 0.1, 'w' => 0.4, 'h' => 0.05],
            'severity' => 'must-fix', 'body' => 'The price wraps on phone.', 'number' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function mcp(string $token, string $tool, array $arguments, array $meta = []): array
    {
        $response = $this->withToken($token)->postJson('/mcp/revisemy', [
            'jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => $arguments, ...($meta === [] ? [] : ['_meta' => $meta])],
        ], ['Accept' => 'application/json, text/event-stream'])->assertOk();

        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/event-stream')) {
            return collect(explode("\n\n", trim($response->streamedContent())))
                ->map(fn (string $event) => json_decode(substr($event, strlen('data: ')), true))
                ->all();
        }

        return [$response->json()];
    }

    // ── wait_for_review ───────────────────────────────────────────────

    public function test_waiting_returns_at_once_when_something_already_moved(): void
    {
        [$user, $review] = $this->setUpReview();
        $this->mark($review);

        ReviseMyServer::actingAs($user)->tool(WaitForReviewTool::class, [
            'id' => $review->public_id,
            'since' => now()->subHour()->toIso8601String(),
        ])->assertHasNoErrors()
            ->assertSee('Something changed on the review.')
            ->assertStructuredContent(fn ($json) => $json->where('changed', true)->etc());
    }

    public function test_waiting_gives_up_after_the_hold_with_nothing_new(): void
    {
        [$user, $review] = $this->setUpReview();

        $started = microtime(true);

        ReviseMyServer::actingAs($user)->tool(WaitForReviewTool::class, [
            'id' => $review->public_id,
            'since' => $review->activityStamp()->toIso8601String(),
        ])->assertHasNoErrors()
            ->assertSee('Nothing changed yet.')
            ->assertStructuredContent(fn ($json) => $json->where('changed', false)->etc());

        $this->assertGreaterThanOrEqual(0.9, microtime(true) - $started);
    }

    public function test_waiting_on_a_closed_review_answers_at_once(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_APPROVED);
        config(['revisemy.wait_max_seconds' => 30]);

        $started = microtime(true);

        ReviseMyServer::actingAs($user)->tool(WaitForReviewTool::class, [
            'id' => $review->public_id,
            'since' => now()->addMinute()->toIso8601String(),
        ])->assertHasNoErrors()->assertStructuredContent(fn ($json) => $json->where('changed', false)->where('status', 'approved')->etc());

        $this->assertLessThan(2, microtime(true) - $started);
    }

    public function test_a_host_that_asks_hears_a_heartbeat_while_it_waits(): void
    {
        [, $review, $token] = $this->setUpReview();

        $events = collect($this->mcp($token, 'wait_for_review', [
            'id' => $review->public_id,
            'since' => $review->activityStamp()->toIso8601String(),
        ], ['progressToken' => 'w-1']));

        $beats = $events->where('method', 'notifications/progress')->pluck('params')->values();

        $this->assertNotEmpty($beats);
        $this->assertSame('w-1', $beats[0]['progressToken']);
        $this->assertStringStartsWith('Waiting for you on Pricing page', $beats[0]['message']);
        $this->assertFalse($events->last()['result']['structuredContent']['changed']);
    }

    public function test_a_host_that_does_not_ask_gets_one_answer(): void
    {
        [, $review, $token] = $this->setUpReview();
        $this->mark($review);

        $events = $this->mcp($token, 'wait_for_review', ['id' => $review->public_id, 'since' => now()->subHour()->toIso8601String()]);

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]['result']['structuredContent']['changed']);
    }

    public function test_the_stamp_agrees_with_updated_at_and_moves_on_a_comment(): void
    {
        [$user, $review] = $this->setUpReview();
        $mark = $this->mark($review);
        $this->travel(5)->seconds();
        app(MarkLifecycleService::class)->addComment($mark, AnnotationComment::AUTHOR_OWNER, 'Which one?');

        ReviseMyServer::actingAs($user)->tool(GetReviewTool::class, ['id' => $review->public_id])
            ->assertStructuredContent(fn ($json) => $json
                ->where('updated_at', $review->activityStamp()->toIso8601String())
                ->where('next_action.wait_with.tool', 'wait_for_review')
                ->where('next_action.wait_with.since', $review->activityStamp()->toIso8601String())
                ->etc());
    }

    public function test_the_api_mirrors_waiting(): void
    {
        [, $review, $token] = $this->setUpReview();
        $this->mark($review);

        $this->withToken($token)
            ->getJson('/api/reviews/'.$review->public_id.'/wait?since='.urlencode(now()->subHour()->toIso8601String()))
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('id', $review->public_id);
    }

    // ── look_at_marks ─────────────────────────────────────────────────

    public function test_the_agent_sees_each_open_mark_drawn_on_its_crop(): void
    {
        [, $review, $token] = $this->setUpReview();
        $this->mark($review);
        $this->mark($review, ['number' => 2, 'severity' => 'nit', 'area' => null, 'x' => 0.5, 'y' => 0.8, 'body' => 'Tighten the FAQ gap.']);

        $result = $this->mcp($token, 'look_at_marks', ['id' => $review->public_id])[0]['result'];
        $content = collect($result['content']);

        $this->assertFalse($result['isError']);
        $this->assertStringContainsString('2 marks on "Pricing page", pass 1', $content[0]['text']);
        $this->assertStringContainsString('M1 · Must fix', $content[1]['text']);

        $images = $content->where('type', 'image')->values();
        $this->assertCount(2, $images);
        $this->assertSame('image/jpeg', $images[0]['mimeType']);

        [$width, $height] = getimagesizefromstring(base64_decode($images[0]['data']));
        $this->assertLessThanOrEqual(1024, max($width, $height));
        $this->assertSame(800, $width, 'the band is the full width of the shot');
    }

    public function test_it_shows_at_most_five_must_fix_first(): void
    {
        [, $review, $token] = $this->setUpReview();

        foreach (range(1, 7) as $n) {
            $this->mark($review, ['number' => $n, 'severity' => $n === 7 ? 'must-fix' : 'nit', 'body' => 'Mark '.$n]);
        }

        $content = collect($this->mcp($token, 'look_at_marks', ['id' => $review->public_id])[0]['result']['content']);

        $this->assertCount(5, $content->where('type', 'image'));
        $this->assertStringStartsWith('M7', $content[1]['text']);
    }

    public function test_a_tall_capture_is_cropped_from_its_vision_copy_or_not_at_all(): void
    {
        [, $review, $token] = $this->setUpReview(image: $this->png(1200, 4000));
        $shot = $review->screenshots()->firstOrFail();
        $this->mark($review, ['area' => ['x' => 0.2, 'y' => 0.02, 'w' => 0.3, 'h' => 0.02], 'y' => 0.03]);
        $this->mark($review, ['number' => 2, 'area' => ['x' => 0.2, 'y' => 0.9, 'w' => 0.3, 'h' => 0.02], 'y' => 0.91, 'body' => 'Footer link is too small.']);

        // No vision copy: neither gets a picture, and both say why.
        $content = collect($this->mcp($token, 'look_at_marks', ['id' => $review->public_id])[0]['result']['content']);
        $this->assertCount(0, $content->where('type', 'image'));
        $this->assertStringContainsString('No picture for this one', $content[1]['text']);

        // A vision copy of the top quarter: the mark up there gets one.
        $vision = imagecreatetruecolor(600, 500);
        ob_start();
        imagejpeg($vision);
        Storage::disk($shot->disk)->put('vision.jpg', (string) ob_get_clean());
        $shot->update(['meta' => [...($shot->meta ?? []), 'vision_path' => 'vision.jpg', 'vision_crop' => 0.25]]);

        $content = collect($this->mcp($token, 'look_at_marks', ['id' => $review->public_id])[0]['result']['content']);
        $this->assertCount(1, $content->where('type', 'image'));
        $this->assertStringContainsString('No picture for this one', $content->last()['text']);
    }

    public function test_a_tall_capture_is_cropped_from_its_slices_anywhere_on_the_page(): void
    {
        [, $review, $token] = $this->setUpReview(image: $this->png(1200, 4000));
        $shot = $review->screenshots()->firstOrFail();
        $this->mark($review, ['area' => ['x' => 0.2, 'y' => 0.9, 'w' => 0.3, 'h' => 0.02], 'y' => 0.91, 'body' => 'Footer link is too small.']);
        // Across the seam between the two slices.
        $this->mark($review, ['number' => 2, 'area' => ['x' => 0.2, 'y' => 0.48, 'w' => 0.3, 'h' => 0.04], 'y' => 0.5, 'body' => 'This card is cut in two.']);

        // Two slices at half the shot's density, each half the page.
        $slices = [];
        foreach ([0, 1] as $index) {
            $slice = imagecreatetruecolor(600, 1000);
            ob_start();
            imagejpeg($slice);
            Storage::disk($shot->disk)->put("slice-{$index}.jpg", (string) ob_get_clean());
            $slices[] = ['path' => "slice-{$index}.jpg", 'top' => $index * 0.5, 'height' => 0.5];
        }
        $shot->update(['meta' => [...($shot->meta ?? []), 'slices' => $slices]]);

        $content = collect($this->mcp($token, 'look_at_marks', ['id' => $review->public_id])[0]['result']['content']);
        $images = $content->where('type', 'image')->values();

        $this->assertCount(2, $images, 'both marks get a picture, far down the page and across the seam');
        $this->assertStringNotContainsString('No picture for this one', $content->pluck('text')->implode("\n"));

        foreach ($images as $image) {
            [$width] = getimagesizefromstring(base64_decode($image['data']));
            $this->assertSame(600, $width, 'the band is the full width of the slice');
        }
    }

    // ── the inline review ─────────────────────────────────────────────

    public function test_the_inline_review_talks_back_to_the_host(): void
    {
        ReviseMyServer::resource(ReviewApp::class)
            ->assertSee(['ui/message', 'ui/update-model-context', 'ui/request-display-mode', 'ui/notifications/size-changed', 'ui/resource-teardown', 'BroadcastChannel', "availableDisplayModes: ['inline', 'fullscreen']"]);
    }

    public function test_each_payload_carries_when_it_was_made(): void
    {
        [, $review] = $this->setUpReview();

        $first = $review->toAgentPayload()['snapshot_at'];
        usleep(2000);
        $second = $review->fresh()->toAgentPayload()['snapshot_at'];

        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}Z$/', $first);
        $this->assertGreaterThan($first, $second);
    }
}
