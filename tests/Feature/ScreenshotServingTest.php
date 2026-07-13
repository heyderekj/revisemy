<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScreenshotServingTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));
    }

    protected function createReviewWithShot(): Review
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $user = app(TryTokenService::class)->create()['user'];
        $token = $user->currentAccessToken()?->plainTextToken
            ?? $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Serve me',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        return Review::query()->with('screenshots')->firstOrFail();
    }

    public function test_signed_screenshot_url_streams_the_image(): void
    {
        $review = $this->createReviewWithShot();
        $shot = $review->screenshots->firstOrFail();

        $this->get($shot->url())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get($shot->thumbUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_unsigned_screenshot_url_is_rejected(): void
    {
        $review = $this->createReviewWithShot();
        $shot = $review->screenshots->firstOrFail();

        $this->get('/shots/'.$shot->id)->assertForbidden();
        $this->get('/shots/'.$shot->id.'/thumb')->assertForbidden();
    }
}
