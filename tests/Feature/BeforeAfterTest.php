<?php

namespace Tests\Feature;

use App\Jobs\GenerateSecondOpinionJob;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\Screenshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BeforeAfterTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    /**
     * @return array{0: string, 1: Review, 2: Annotation}
     */
    protected function setUpReviewWithMark(int $screenshots = 1): array
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Before/after',
            'images' => array_fill(0, $screenshots, $this->tinyPngDataUrl()),
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED, 'decision_at' => now()]);

        $mark = $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5,
            'area' => ['x' => 0.4, 'y' => 0.4, 'w' => 0.2, 'h' => 0.2],
            'severity' => 'must-fix', 'body' => 'Fix', 'number' => 1,
        ]);

        return [$token, $review, $mark];
    }

    public function test_resolving_with_after_image_links_an_after_screenshot(): void
    {
        [$token, $review, $mark] = $this->setUpReviewWithMark();
        Queue::fake(); // reset pushes from creation

        $response = $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [
                ['id' => $mark->id, 'status' => 'resolved', 'note' => 'Fixed.', 'after_image' => $this->tinyPngDataUrl()],
            ],
        ])->assertOk();

        $mark->refresh();
        $this->assertNotNull($mark->after_screenshot_id);
        $this->assertSame(Screenshot::KIND_AFTER, $mark->afterScreenshot->kind);

        // After shots never join the reviewable set or trigger a second opinion.
        $this->assertSame(1, $review->fresh()->screenshots()->count());
        Queue::assertNotPushed(GenerateSecondOpinionJob::class);

        // The work packet points the human/agent at the evidence.
        $pin = collect($response->json('review.work_packets.pins'))->firstWhere('id', $mark->id);
        $this->assertNotNull($pin['after_screenshot_url']);
    }

    public function test_after_image_works_even_when_review_has_five_screenshots(): void
    {
        [$token, $review, $mark] = $this->setUpReviewWithMark(screenshots: 5);

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [
                ['id' => $mark->id, 'status' => 'resolved', 'after_image' => $this->tinyPngDataUrl()],
            ],
        ])->assertOk();

        $this->assertNotNull($mark->fresh()->after_screenshot_id);
        $this->assertSame(5, $review->fresh()->screenshots()->count());
    }

    public function test_resolving_without_after_image_still_works(): void
    {
        [$token, $review, $mark] = $this->setUpReviewWithMark();

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Done.']],
        ])->assertOk();

        $mark->refresh();
        $this->assertSame(Annotation::STATUS_RESOLVED, $mark->status);
        $this->assertNull($mark->after_screenshot_id);
    }

    public function test_invalid_after_image_returns_validation_error(): void
    {
        [$token, $review, $mark] = $this->setUpReviewWithMark();

        $this->withToken($token)->postJson('/api/reviews/'.$review->public_id.'/marks/resolve', [
            'marks' => [
                ['id' => $mark->id, 'status' => 'resolved', 'after_image' => 'not-an-image'],
            ],
        ])->assertUnprocessable();

        $this->assertNull($mark->fresh()->after_screenshot_id);
    }
}
