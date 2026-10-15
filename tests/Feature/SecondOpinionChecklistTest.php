<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Review;
use App\Services\SecondOpinionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The checklist is one set per review, kept on the first shot. Vision stays per shot.
 */
class SecondOpinionChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function pngDataUrl(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    protected function makeReview(string $type = Review::TYPE_WEBSITE): Review
    {
        Storage::fake('public');
        Queue::fake();
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => true,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Three viewports',
            'type' => $type,
            'images' => [
                $this->pngDataUrl(1280, 800),
                $this->pngDataUrl(375, 812),
                $this->pngDataUrl(768, 1024),
            ],
        ])->assertCreated();

        return Review::query()->with('screenshots.findings')->firstOrFail();
    }

    protected function withoutVision(): void
    {
        config([
            'revisemy.vision.provider' => 'auto',
            'revisemy.openai.api_key' => null,
            'revisemy.openai.base_url' => null,
            'revisemy.anthropic.api_key' => null,
        ]);
    }

    public function test_a_multi_shot_review_gets_one_checklist_on_the_first_shot(): void
    {
        $this->withoutVision();
        $review = $this->makeReview();
        [$desktop, $mobile, $tablet] = $review->screenshots->all();

        $checklist = $desktop->findings->where('source', Finding::SOURCE_CHECKLIST);
        $this->assertNotEmpty($checklist);
        $this->assertSame(0, $mobile->findings->count());
        $this->assertSame(0, $tablet->findings->count());

        // Viewport notes come from every shot: the mobile shot counts, so no nudge to add one.
        $bodies = $checklist->pluck('body')->implode("\n");
        $this->assertStringContainsString('Mobile viewport', $bodies);
        $this->assertStringNotContainsString('also review a mobile capture', $bodies);

        foreach ($review->screenshots as $shot) {
            $this->assertSame('ready', $shot->second_opinion_status);
        }
    }

    public function test_a_later_shot_points_the_owner_to_the_checklist(): void
    {
        $this->withoutVision();
        $review = $this->makeReview();

        Livewire::test('review-page', ['token' => $review->token])
            ->assertDontSee('so it sits on the first one')
            ->call('selectScreenshot', 1)
            ->assertSee('so it sits on the first one')
            ->call('selectScreenshot', 0)
            ->assertDontSee('so it sits on the first one');
    }

    public function test_refreshing_a_later_shot_leaves_the_checklist_alone(): void
    {
        $this->withoutVision();
        $review = $this->makeReview();
        [$first, $second] = $review->screenshots->all();

        $kept = $first->findings->where('source', Finding::SOURCE_CHECKLIST)->pluck('id')->sort()->values()->all();

        // A copy left on a later shot from before the checklist moved.
        $second->findings()->create([
            'source' => Finding::SOURCE_CHECKLIST,
            'severity' => Finding::SEVERITY_SUGGESTION,
            'body' => 'An older checklist copy.',
        ]);

        app(SecondOpinionService::class)->requestForReview($review, 1);

        $this->assertSame(0, $second->findings()->count());
        $this->assertSame(
            $kept,
            $first->findings()->where('source', Finding::SOURCE_CHECKLIST)->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_refreshing_every_shot_still_yields_one_checklist(): void
    {
        $this->withoutVision();
        $review = $this->makeReview();
        $before = Finding::query()->where('source', Finding::SOURCE_CHECKLIST)->count();

        app(SecondOpinionService::class)->requestForReview($review);

        $this->assertSame($before, Finding::query()->where('source', Finding::SOURCE_CHECKLIST)->count());
        $this->assertSame(
            [$review->screenshots->first()->id],
            Finding::query()->where('source', Finding::SOURCE_CHECKLIST)->distinct()->pluck('screenshot_id')->all(),
        );
    }

    public function test_vision_on_a_later_shot_is_deduped_against_the_review_checklist(): void
    {
        config([
            'revisemy.vision.provider' => 'anthropic',
            'revisemy.anthropic.api_key' => 'test-key',
            'revisemy.openai.api_key' => null,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [[
                'type' => 'text',
                'text' => json_encode(['findings' => [
                    // Near-verbatim copy of a ui checklist item — should be dropped.
                    ['severity' => 'suggestion', 'body' => 'Check visual hierarchy: is there one clear primary action, or do multiple elements compete for attention here?', 'area' => null],
                    ['severity' => 'polish', 'body' => 'The footer links sit too close to the cookie banner.', 'area' => ['x' => 0.1, 'y' => 0.8, 'w' => 0.5, 'h' => 0.1]],
                ]]),
            ]]]),
        ]);

        $review = $this->makeReview(Review::TYPE_UI);
        $second = $review->screenshots->get(1);

        app(SecondOpinionService::class)->generate($second);

        $bodies = $second->findings()->pluck('body')->all();
        $this->assertSame(['The footer links sit too close to the cookie banner.'], $bodies);
    }
}
