<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Screenshot;
use App\Services\Capture\CaptureDevice;
use App\Services\Capture\CaptureSettleScript;
use App\Services\Capture\HostedCaptureDriver;
use App\Services\PdfReviewService;
use App\Services\ScreenshotStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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

        $budget = app(HostedCaptureDriver::class)->functionBudget(sweep: true, collect: true);
        Http::assertSent(function ($request) use ($budget) {
            $settle = (string) ($request->data()['context']['settle'] ?? '');

            return str_contains($settle, '"sweepBudgetMs":'.$budget['sweepMs'])
                && str_contains($settle, '"loadBudgetMs":'.$budget['loadMs']);
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

    public function test_a_viewport_that_never_answers_can_be_replaced_without_losing_other_shots(): void
    {
        $token = $this->setUpEnv();

        Http::fake(function ($request) {
            if (($request->data()['context']['viewport']['width'] ?? null) === 375) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response(['image' => base64_encode($this->encoded('png', 4, 4))]);
        });

        $desktop = $this->capture($token);
        $review = Review::query()->sole();
        $phone = $review->screenshots()->get()[1];

        $this->assertSame('capture', $desktop->meta['origin']);
        $this->assertSame('failed', $phone->captureQuality()['status']);
        $this->assertSame('mobile-375', $phone->meta['viewport']);
        $this->get($review->reviewUrl())->assertOk();
        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/screenshots', [
            'image' => base64_encode($this->encoded('png', 375, 812)),
            'replace_id' => $phone->id,
        ])->assertOk()
            ->assertJsonPath('screenshots.0.id', $desktop->id)
            ->assertJsonPath('screenshots.1.meta.viewport', 'mobile-375')
            ->assertJsonPath('screenshots.1.capture_quality', null)
            ->assertJsonCount(3, 'screenshots');
        $this->assertModelMissing($phone);
    }

    public function test_a_follow_up_reuses_the_previous_viewports_and_capture_choices(): void
    {
        $token = $this->setUpEnv();
        Http::fake([
            'capture.test/function*' => Http::response([
                'image' => base64_encode($this->encoded('png', 4, 4)),
                'loaded' => true,
                'stable' => true,
            ]),
        ]);
        $desktop = $this->capture($token);
        config([
            'revisemy.capture.viewports' => ['desktop' => [1000, 600]],
            'revisemy.capture.url_full_page' => false,
            'revisemy.capture.hide_consent' => false,
        ]);

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Follow-up',
            'parent_id' => $desktop->review->public_id,
            'capture_url' => true,
        ])->assertCreated()
            ->assertJsonCount(3, 'screenshots')
            ->assertJsonPath('screenshots.0.capture_quality.status', 'ready')
            ->assertJsonPath('screenshots.0.meta.capture_profile', $desktop->meta['capture_profile'])
            ->assertJsonPath('screenshots.0.comparison.url', $desktop->url())
            ->assertJsonPath('screenshots.0.comparison.warning', null);
    }

    public function test_a_missing_phone_asset_is_visible_before_the_reviewer_marks_the_capture(): void
    {
        $token = $this->setUpEnv();
        Http::fake(function ($request) {
            $missing = $request->data()['context']['viewport']['width'] === 375 ? 1 : 0;

            return Http::response([
                'image' => base64_encode($this->encoded('png', 4, 4)),
                'loaded' => $missing === 0,
                'stable' => true,
                'quality' => ['incomplete_images' => $missing],
            ]);
        });

        $review = $this->capture($token)->review;
        $this->withToken($token)->getJson('/api/reviews/'.$review->public_id)
            ->assertJsonPath('screenshots.0.capture_quality.status', 'ready')
            ->assertJsonPath('screenshots.1.capture_quality.status', 'partial');

        Livewire::test('review-page', ['token' => $review->token])
            ->assertSee('Capture ready')
            ->call('selectScreenshot', 1)
            ->assertSee('Partial capture')
            ->assertSee('Images did not load (1).');
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

    public function test_pdf_capture_preserves_distinct_page_contents_and_sizes(): void
    {
        $token = $this->setUpEnv();

        // A three-page PDF with differently sized, solid red, green and blue pages.
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R] /Count 3 >>'];
        foreach ([[240, 120, '1 0 0'], [120, 240, '0 1 0'], [180, 180, '0 0 1']] as $index => [$w, $h, $color]) {
            $stream = "$color rg 0 0 $w $h re f\n";
            $contents = 4 + $index * 2;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Resources <<>> /Contents $contents 0 R >>";
            $objects[] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = '';
        foreach ($objects as $index => $object) {
            $offsets .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n".$offsets."trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Mixed page sizes',
            'pdf' => base64_encode($pdf),
        ])->assertCreated()->assertJsonCount(3, 'screenshots');
        $review = Review::query()->sole();
        $shots = $review->screenshots()->get();
        $disk = Storage::disk($review->pdf_disk);
        foreach ($shots as $shot) {
            $disk->assertMissing($shot->path);
        }

        $this->assertSame([1, 2, 3], $shots->pluck('meta.page')->all());
        foreach ([[640, 320, [255, 0, 0]], [320, 640, [0, 255, 0]], [480, 480, [0, 0, 255]]] as $index => [$width, $height, $rgb]) {
            $shot = $shots[$index];
            app(PdfReviewService::class)->ensurePageImage($shot);
            $image = imagecreatefromstring($disk->get($shot->path));
            $this->assertSame([$width, $height], [imagesx($image), imagesy($image)]);
            $pixel = imagecolorsforindex($image, imagecolorat($image, 20, 20));
            $this->assertSame($rgb, [$pixel['red'], $pixel['green'], $pixel['blue']]);
        }
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
