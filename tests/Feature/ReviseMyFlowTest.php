<?php

namespace Tests\Feature;

use App\Jobs\GenerateSecondOpinionJob;
use App\Models\Finding;
use App\Models\Review;
use App\Models\User;
use App\Services\SecondOpinionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ReviseMyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

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

        $reviewResponse = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Hero pass',
            'context' => 'Check the CTA',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        $reviewResponse
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('status_label', 'Waiting on your eye');

        $this->assertStringContainsString('Apply human pins first', (string) $reviewResponse->json('guidance'));

        $url = $reviewResponse->json('review_url');
        $this->assertStringContainsString('/r/', $url);

        $this->get($url)->assertOk()->assertSee('Hero pass');

        $review = Review::query()->firstOrFail();
        $this->assertTrue($review->isOpenForFeedback());
        $this->assertNotNull($review->expires_at);
    }

    public function test_creating_a_review_queues_second_opinion_job(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');

        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Queue check',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        Queue::assertPushed(GenerateSecondOpinionJob::class);
    }

    public function test_checklist_second_opinion_writes_findings_without_changing_status(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => true,
            'revisemy.openai.api_key' => null,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');

        $payload = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'CTA review',
            'context' => 'Check the CTA contrast',
            'page_url' => 'https://example.com/demo',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        $this->assertSame('pending', $payload['status']);
        $this->assertNotEmpty($payload['work_packets']['second_opinion']);
        $this->assertSame('pending', $payload['status']);

        $sources = collect($payload['work_packets']['second_opinion'])->pluck('source')->unique()->all();
        $this->assertContains(Finding::SOURCE_CHECKLIST, $sources);

        foreach ($payload['work_packets']['second_opinion'] as $finding) {
            $this->assertContains($finding['severity'], ['suggestion', 'a11y', 'polish']);
        }
    }

    public function test_add_findings_stores_agent_suggestions(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');

        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Subagent',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $response = $this->withToken($token)->postJson('/api/reviews/'.$id.'/findings', [
            'findings' => [
                [
                    'severity' => 'a11y',
                    'body' => 'Icon button needs an accessible name.',
                    'area' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.15, 'h' => 0.1],
                    'screenshot_index' => 0,
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('status', 'pending');
        $this->assertCount(1, $response->json('work_packets.second_opinion'));
        $this->assertSame('agent', $response->json('work_packets.second_opinion.0.source'));
        $this->assertSame('a11y', $response->json('work_packets.second_opinion.0.severity'));
        $this->assertEmpty($response->json('work_packets.pins'));
    }

    public function test_to_agent_payload_separates_pins_from_second_opinion(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Packets',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $shot->annotations()->create([
            'x' => 0.5,
            'y' => 0.5,
            'severity' => 'must-fix',
            'body' => 'Human says fix this',
            'number' => 1,
        ]);

        app(SecondOpinionService::class)->addAgentFindings($review, [
            [
                'severity' => 'polish',
                'body' => 'Agent hint only',
                'screenshot_index' => 0,
            ],
        ]);

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings'])->toAgentPayload();

        $this->assertCount(1, $payload['work_packets']['pins']);
        $this->assertSame('must-fix', $payload['work_packets']['pins'][0]['severity']);
        $this->assertCount(1, $payload['work_packets']['second_opinion']);
        $this->assertSame('agent', $payload['work_packets']['second_opinion'][0]['source']);
        $this->assertSame('pending', $payload['status']);
    }

    public function test_reviews_are_scoped_to_try_token(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $tokenA = $this->postJson('/api/try-token')->json('token');
        $tokenB = $this->postJson('/api/try-token')->json('token');

        $userA = PersonalAccessToken::findToken($tokenA)?->tokenable;
        $userB = PersonalAccessToken::findToken($tokenB)?->tokenable;

        $this->assertInstanceOf(User::class, $userA);
        $this->assertInstanceOf(User::class, $userB);
        $this->assertNotSame($userA->workspace_id, $userB->workspace_id);

        $id = $this->actingAs($userA, 'sanctum')->postJson('/api/reviews', [
            'title' => 'Only A',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $this->actingAs($userB, 'sanctum')->getJson('/api/reviews/'.$id)->assertNotFound();
        $this->actingAs($userA, 'sanctum')->getJson('/api/reviews/'.$id)->assertOk();
    }
}
