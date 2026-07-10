<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ReviseMyFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Pin feedback for your agent');
    }

    public function test_try_token_create_review_and_open_secret_link(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);

        $tokenResponse = $this->postJson('/api/try-token')->assertCreated();
        $token = $tokenResponse->json('token');

        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        $reviewResponse = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Hero pass',
            'context' => 'Check the CTA',
            'images' => ['data:image/png;base64,'.$png],
        ])->assertCreated();

        $reviewResponse
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('status_label', 'Waiting on your eye');

        $url = $reviewResponse->json('review_url');
        $this->assertStringContainsString('/r/', $url);

        $this->get($url)->assertOk()->assertSee('Hero pass');

        $review = Review::query()->firstOrFail();
        $this->assertTrue($review->isOpenForFeedback());
        $this->assertNotNull($review->expires_at);
    }

    public function test_reviews_are_scoped_to_try_token(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);

        $tokenA = $this->postJson('/api/try-token')->json('token');
        $tokenB = $this->postJson('/api/try-token')->json('token');

        $userA = PersonalAccessToken::findToken($tokenA)?->tokenable;
        $userB = PersonalAccessToken::findToken($tokenB)?->tokenable;

        $this->assertInstanceOf(User::class, $userA);
        $this->assertInstanceOf(User::class, $userB);
        $this->assertNotSame($userA->workspace_id, $userB->workspace_id);

        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        $id = $this->actingAs($userA, 'sanctum')->postJson('/api/reviews', [
            'title' => 'Only A',
            'images' => ['data:image/png;base64,'.$png],
        ])->json('id');

        $this->actingAs($userB, 'sanctum')->getJson('/api/reviews/'.$id)->assertNotFound();
        $this->actingAs($userA, 'sanctum')->getJson('/api/reviews/'.$id)->assertOk();
    }
}
