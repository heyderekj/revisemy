<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\DecideReviewTool;
use App\Models\Review;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    protected function setUpWorkspace(): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $try = app(TryTokenService::class)->create();

        return [$try['workspace'], $try['user']];
    }

    public function test_decision_posts_signed_webhook(): void
    {
        Http::fake(['ci.example.test/*' => Http::response('ok')]);

        [$workspace, $user] = $this->setUpWorkspace();

        $review = app(ReviewService::class)->create(
            $workspace, 'Hero pass', null, [$this->tinyPngDataUrl()],
            webhookUrl: 'https://ci.example.test/hooks/revisemy',
        );

        ReviseMyServer::actingAs($user)->tool(DecideReviewTool::class, [
            'review_id' => $review->public_id,
            'decision' => 'approved',
        ])->assertHasNoErrors();

        Http::assertSent(function (ClientRequest $request) use ($review) {
            $body = $request->body();
            $payload = json_decode($body, true);

            return $request->url() === 'https://ci.example.test/hooks/revisemy'
                && $request->header('X-ReviseMy-Event') === ['review.decided']
                && $request->header('X-ReviseMy-Review') === [$review->public_id]
                && $request->header('X-ReviseMy-Signature') === ['sha256='.hash_hmac('sha256', $body, $review->token)]
                && $payload['event'] === 'review.decided'
                && $payload['review']['status'] === 'approved'
                && $payload['review']['id'] === $review->public_id;
        });
    }

    public function test_no_webhook_url_means_no_request(): void
    {
        Http::fake();

        [$workspace] = $this->setUpWorkspace();

        $review = app(ReviewService::class)->create($workspace, 'Quiet pass', null, [$this->tinyPngDataUrl()]);
        app(ReviewService::class)->decide($review, Review::STATUS_APPROVED);

        Http::assertNothingSent();
    }

    public function test_next_pass_inherits_the_webhook_url(): void
    {
        Http::fake(['ci.example.test/*' => Http::response('ok')]);

        [$workspace] = $this->setUpWorkspace();
        $service = app(ReviewService::class);

        $parent = $service->create(
            $workspace, 'Pass 1', null, [$this->tinyPngDataUrl()],
            webhookUrl: 'https://ci.example.test/hooks/revisemy',
        );
        $service->decide($parent, Review::STATUS_CHANGES_REQUESTED);

        $child = $service->create($workspace, 'Pass 2', null, [$this->tinyPngDataUrl()], parentPublicId: $parent->public_id);

        $this->assertSame('https://ci.example.test/hooks/revisemy', $child->webhook_url);

        $service->decide($child, Review::STATUS_APPROVED);
        Http::assertSentCount(2); // one per decision
    }

    public function test_invalid_webhook_url_is_rejected_via_rest(): void
    {
        [, $user] = $this->setUpWorkspace();

        $this->actingAs($user, 'sanctum')->postJson('/api/reviews', [
            'title' => 'Bad hook',
            'images' => [$this->tinyPngDataUrl()],
            'webhook_url' => 'ftp://ci.example.test/hook',
        ])->assertUnprocessable()->assertJsonValidationErrors('webhook_url');
    }

    public function test_webhook_url_never_leaks_in_payloads(): void
    {
        [$workspace] = $this->setUpWorkspace();

        $review = app(ReviewService::class)->create(
            $workspace, 'Secret hook', null, [$this->tinyPngDataUrl()],
            webhookUrl: 'https://ci.example.test/hooks/secret-token-abc',
        );

        $this->assertStringNotContainsString(
            'secret-token-abc',
            json_encode([$review->toAgentPayload(), $review->toArray()]),
        );
    }
}
