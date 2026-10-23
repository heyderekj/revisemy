<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Screenshot;
use App\Services\Capture\CaptureDevice;
use App\Services\Capture\CaptureSettleScript;
use App\Services\Capture\HostedCaptureDriver;
use App\Services\DocumentIngestionService;
use App\Services\ScreenshotStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What keeps a capture sharp and whole: a session that fits the plan, WebP
 * before shrinking, crop slices for long pages, chat launchers out of the
 * way, a measured fallback, and DPRs that say what the pixels hold.
 */
class CaptureQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpEnv(): string
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.capture.driver' => 'hosted',
            'revisemy.capture.endpoint' => 'https://capture.test/screenshot',
            'revisemy.capture.api_key' => 'cap-key',
        ]);
        Queue::fake();

        return $this->postJson('/api/try-token')->json('token');
    }

    protected function encoded(string $type, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        match ($type) {
            'webp' => imagewebp($image, null, 80),
            'jpeg' => imagejpeg($image, null, 80),
            default => imagepng($image),
        };

        return (string) ob_get_clean();
    }

    protected function capture(string $token): Screenshot
    {
        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Long page',
            'page_url' => 'https://example.com',
            'capture_url' => true,
        ])->assertCreated();

        return Review::query()->firstOrFail()->screenshots()->orderBy('sort_order')->firstOrFail();
    }

    public function test_the_default_budget_fits_a_two_minute_plan_by_shortening_the_sweep(): void
    {
        $budget = app(HostedCaptureDriver::class)->functionBudget(sweep: true, collect: true);

        $this->assertSame(120_000, $budget['sessionMs']);
        $this->assertLessThan(45_000, $budget['sweepMs']);
        $this->assertSame(8_000, $budget['loadMs'], 'the sweep gives way before the load gate');
        $this->assertSame(16_000, $budget['shootMs']);
        $this->assertSame(125, $budget['httpTimeout']);
    }

    public function test_a_shorter_plan_takes_time_from_the_sweep_then_the_load_gate_then_idle_then_shots(): void
    {
        config(['revisemy.capture.session_max_ms' => 60_000]);

        $budget = app(HostedCaptureDriver::class)->functionBudget(sweep: true, collect: true);

        $this->assertSame(['sweepMs' => 10_000, 'loadMs' => 3_000, 'idleMs' => 1_000, 'shootMs' => 8_000], array_intersect_key($budget, array_flip(['sweepMs', 'loadMs', 'idleMs', 'shootMs'])));
        $this->assertSame(60_000, $budget['sessionMs']);
    }

    public function test_without_a_plan_cap_every_budget_stays_whole(): void
    {
        config(['revisemy.capture.session_max_ms' => null]);

        $budget = app(HostedCaptureDriver::class)->functionBudget(sweep: true, collect: true);

        $this->assertSame(45_000, $budget['sweepMs']);
        $this->assertGreaterThan(120_000, $budget['sessionMs']);
    }

    public function test_the_settle_script_is_told_the_shortened_budgets(): void
    {
        $token = $this->setUpEnv();
        Http::fake(['capture.test/function*' => Http::response(['image' => base64_encode($this->encoded('png', 4, 4))])]);

        $this->capture($token);

        Http::assertSent(function ($request) {
            $settle = (string) ($request->data()['context']['settle'] ?? '');

            return str_contains($settle, '"sweepBudgetMs":41000') && str_contains($settle, '"loadBudgetMs":8000');
        });
    }

    public function test_each_viewport_keeps_the_phase_timings_its_session_sent_back(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.concurrency' => 3]);
        $widths = [];

        Http::fake(function ($request) use (&$widths) {
            $widths[] = $request->data()['context']['viewport']['width'] ?? null;

            return Http::response(['image' => base64_encode($this->encoded('png', 4, 4)), 'timings' => ['goto' => 2100, 'settle' => 9400, 'settle_sweep' => 6200, 'main' => 800]]);
        });

        $desktop = $this->capture($token);

        $this->assertSame([1280, 375, 768], $widths);
        $this->assertSame(['goto' => 2100, 'settle' => 9400, 'settle_sweep' => 6200, 'main' => 800], $desktop->meta['timings']);
        $this->assertIsInt($desktop->meta['capture_ms']);
    }

    public function test_a_viewport_over_the_plans_browser_limit_is_asked_again_on_its_own(): void
    {
        $token = $this->setUpEnv();
        $asked = [];

        Http::fake(function ($request) use (&$asked) {
            $width = $request->data()['context']['viewport']['width'];
            $asked[] = $width;

            // The plan runs two browsers; the third is turned away once.
            return $width === 768 && count(array_keys($asked, 768)) === 1
                ? Http::response('Too many requests', 429)
                : Http::response(['image' => base64_encode($this->encoded('png', 4, 4))]);
        });

        $this->capture($token);

        $this->assertSame([1280, 375, 768, 768], $asked);
        $this->assertSame(3, Review::query()->sole()->screenshots()->count());
        $this->assertSame('tablet-768', Review::query()->sole()->screenshots()->orderBy('sort_order')->get()[2]->meta['viewport']);
    }

    public function test_a_viewport_that_never_answers_opens_the_review_without_a_shot(): void
    {
        $token = $this->setUpEnv();

        Http::fake(function ($request) {
            if (($request->data()['context']['viewport']['width'] ?? null) === 375) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response(['image' => base64_encode($this->encoded('png', 4, 4))]);
        });

        $shot = $this->capture($token);

        $this->assertSame('capture_failed', $shot->meta['origin']);
        $this->assertStringContainsString('capture_provider_failed', (string) Review::query()->sole()->context);
        $this->assertStringContainsString('mobile-375', (string) Review::query()->sole()->context);
    }

    public function test_bottom_bars_are_placed_at_the_end_of_the_page_unless_turned_off(): void
    {
        $this->assertStringContainsString('"placeFixed":true', CaptureSettleScript::functionBody());
        $this->assertStringContainsString('placeFixed();', CaptureSettleScript::functionBody());

        config(['revisemy.capture.place_fixed' => false]);

        $this->assertStringContainsString('"placeFixed":false', CaptureSettleScript::functionBody());
    }

    public function test_a_webp_reply_is_kept_as_webp_with_the_dpr_it_holds(): void
    {
        $token = $this->setUpEnv();

        Http::fake(['capture.test/function*' => Http::response([
            'image' => base64_encode($this->encoded('webp', 2304, 1800)),
            'format' => 'webp',
            'scale' => 0.9,
            'css' => ['width' => 1280, 'height' => 1000],
            'loaded' => true,
            'stable' => true,
        ])]);

        $desktop = $this->capture($token);

        $this->assertStringEndsWith('.webp', $desktop->path);
        $this->assertSame('webp', $desktop->meta['format']);
        $this->assertSame(1.8, $desktop->meta['dpr']);
        $this->assertSame(['w' => 1280.0, 'h' => 1000.0], $desktop->cssSize());
        $this->assertSame('image/webp', $this->get($desktop->url())->headers->get('Content-Type'));
    }

    public function test_crop_slices_are_kept_as_shares_of_the_page_and_never_shown_to_the_agent(): void
    {
        $token = $this->setUpEnv();
        $slice = base64_encode($this->encoded('jpeg', 640, 1000));

        Http::fake(['capture.test/function*' => Http::response([
            'image' => base64_encode($this->encoded('png', 8, 8)),
            'css' => ['width' => 1280, 'height' => 4000],
            'slices' => [
                ['image' => $slice, 'top' => 0, 'height' => 3125],
                ['image' => $slice, 'top' => 3125, 'height' => 875],
                ['image' => 'not base64 !!', 'top' => 4000, 'height' => 1],
            ],
        ])]);

        $desktop = $this->capture($token);
        $slices = $desktop->meta['slices'];

        $this->assertCount(2, $slices);
        $this->assertSame([0, 0.78125], [$slices[0]['top'], $slices[0]['height']]);
        $this->assertSame([0.78125, 0.21875], [$slices[1]['top'], $slices[1]['height']]);
        Storage::disk('public')->assertExists($slices[1]['path']);

        $payload = $this->withToken($token)->getJson('/api/reviews/'.Review::query()->firstOrFail()->public_id)->json('screenshots.0.meta');
        $this->assertArrayNotHasKey('slices', $payload);
        $this->assertArrayNotHasKey('vision_path', $payload);
    }

    public function test_chat_launchers_can_be_left_alone(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.hide_chat' => false]);
        Http::fake(['capture.test/function*' => Http::response(['image' => base64_encode($this->encoded('png', 4, 4))])]);

        $this->capture($token);

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['context']['settle'] ?? ''), '"hideChat":false'));
    }

    public function test_an_empty_desktop_user_agent_keeps_the_browser_own(): void
    {
        config(['revisemy.capture.desktop_user_agent' => '']);

        $this->assertNull(CaptureDevice::userAgent(['mobile' => false]));
    }

    public function test_the_fallback_measures_the_page_first_and_shoots_a_short_one_at_2x(): void
    {
        $token = $this->setUpEnv();
        config(['revisemy.capture.content_endpoint' => 'https://capture.test/content']);
        $order = [];

        Http::fake(function ($request) use (&$order) {
            $order[] = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_contains($request->url(), '/function') => Http::response('Not Found', 404),
                str_contains($request->url(), '/content') => Http::response('<html><body><h1>Hi</h1><script type="application/json" id="__rm-elements">'
                    .json_encode(['version' => 2, 'docWidth' => 1280, 'docHeight' => 2400, 'viewport' => ['width' => 1280, 'height' => 800], 'elements' => [
                        ['selector' => 'h1', 'tag' => 'h1', 'kind' => 'Heading', 'text' => 'Hi', 'box' => ['x' => 0, 'y' => 0, 'w' => 100, 'h' => 40]],
                    ]]).'</script></body></html>'),
                default => Http::response($this->encoded('png', 4, 4)),
            };
        });

        $desktop = $this->capture($token);

        $this->assertSame(['/function', '/function', '/function', '/content', '/screenshot', '/screenshot', '/screenshot'], $order, 'measured once, before the shots, and never again');
        $this->assertSame(2, $desktop->meta['dpr']);
        $this->assertSame('separate_load', $desktop->meta['elements_source']);
        $this->assertStringContainsString('<h1>Hi</h1>', (string) Review::query()->firstOrFail()->domHtml());

        Http::assertSent(fn ($request) => str_contains($request->url(), '/screenshot')
            && ($request->data()['viewport']['width'] ?? null) === 768
            && ($request->data()['viewport']['deviceScaleFactor'] ?? null) === 2);
    }

    public function test_measured_dpr_keeps_a_long_page_at_1x_and_leaves_phones_alone(): void
    {
        $desktop = ['width' => 1280, 'dpr' => 2];
        $phone = ['width' => 375, 'dpr' => 2];

        $this->assertSame(2, CaptureDevice::measuredDpr($desktop, ['width' => 1280, 'height' => 3000]));
        $this->assertSame(1, CaptureDevice::measuredDpr($desktop, ['width' => 1280, 'height' => 9000]));
        $this->assertSame(1, CaptureDevice::measuredDpr($desktop, null));
        // A tablet lays the desktop page out taller; 3,000px at 1280 is about 5,000 at 768.
        $this->assertSame(2, CaptureDevice::measuredDpr(['width' => 768, 'dpr' => 2], ['width' => 1280, 'height' => 3000]));
        $this->assertSame(1, CaptureDevice::measuredDpr(['width' => 768, 'dpr' => 2], ['width' => 1280, 'height' => 4000]));
        $this->assertSame(2, CaptureDevice::measuredDpr($phone, ['width' => 1280, 'height' => 20000]));
    }

    public function test_pdf_pages_render_at_2x_screen_density_within_the_pixel_cap(): void
    {
        // A 16:9 slide (960×540pt) and US Letter keep 192 DPI; an A2 poster drops.
        $this->assertSame(192, DocumentIngestionService::renderDpi(960, 540));
        $this->assertSame(192, DocumentIngestionService::renderDpi(612, 792));
        $poster = DocumentIngestionService::renderDpi(1191, 1684);
        $this->assertLessThan(192, $poster);
        $this->assertLessThanOrEqual(4_000_000, ($poster * 1191 / 72) * ($poster * 1684 / 72));
        $this->assertSame(72, DocumentIngestionService::renderDpi(5000, 5000), 'never under 72');
    }

    public function test_a_capture_shrunk_to_fit_the_cap_says_what_its_pixels_hold(): void
    {
        $token = $this->setUpEnv();

        // Noise doesn't compress, so this PNG is far over a 40KB cap.
        $image = imagecreatetruecolor(300, 300);
        for ($x = 0; $x < 300; $x++) {
            for ($y = 0; $y < 300; $y++) {
                imagesetpixel($image, $x, $y, random_int(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();

        $storage = new class extends ScreenshotStorage
        {
            protected function captureCap(): int
            {
                return 40_000;
            }
        };

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Shrunk',
            'images' => ['data:image/png;base64,'.base64_encode($this->encoded('png', 4, 4))],
        ])->assertCreated();
        $review = Review::query()->firstOrFail();
        $shot = $storage->storeRaw($review, $binary, 0, ['dpr' => 2]);

        $this->assertLessThan(2.0, $shot->meta['dpr']);
        $this->assertSame(round(2 * $shot->width / 300, 2), $shot->meta['dpr']);
        $this->assertContains($shot->meta['format'], ['png', 'webp', 'jpeg']);
    }

    public function test_a_capture_gets_the_time_its_sessions_can_take(): void
    {
        $token = $this->setUpEnv();
        Http::fake(['capture.test/function*' => Http::response(['image' => base64_encode($this->encoded('png', 4, 4))])]);

        $previous = ini_get('max_execution_time');
        $this->beforeApplicationDestroyed(fn () => set_time_limit((int) $previous));

        set_time_limit(30);
        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Images only',
            'images' => ['data:image/png;base64,'.base64_encode($this->encoded('png', 4, 4))],
        ])->assertCreated();
        $this->assertSame('30', ini_get('max_execution_time'), 'an upload needs no more time');

        $this->capture($token);
        // Three viewports and the DOM load, each a whole session (125s).
        $this->assertSame((string) (125 * 4 + 60), ini_get('max_execution_time'));
    }
}
