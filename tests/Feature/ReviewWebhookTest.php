<?php

namespace Tests\Feature;

use App\Events\ReviewDecided;
use App\Listeners\SendReviewWebhook;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\DecideReviewTool;
use App\Models\Review;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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

    private function hookedReview(string $url = 'https://ci.example.test/hooks/revisemy'): Review
    {
        [$workspace] = $this->setUpWorkspace();

        return app(ReviewService::class)->create($workspace, 'Hooked', null, [$this->tinyPngDataUrl()], webhookUrl: $url);
    }

    /** One delivery, the way the queue runs it: handle, then failed() once retries run out. */
    private function deliver(Review $review): void
    {
        $listener = new SendReviewWebhook;
        $event = new ReviewDecided($review);

        try {
            $listener->handle($event);
        } catch (RuntimeException $e) {
            $listener->failed($event, $e);
        }
    }

    public function test_a_webhook_pointing_inside_the_network_is_refused(): void
    {
        [$workspace] = $this->setUpWorkspace();

        $this->expectException(ValidationException::class);

        app(ReviewService::class)->create($workspace, 'Sneaky', null, [$this->tinyPngDataUrl()], webhookUrl: 'http://169.254.169.254/latest/meta-data');
    }

    public function test_a_redirect_is_a_failure_not_a_hop(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        $review = $this->hookedReview();

        $this->deliver($review);

        Http::assertSentCount(1);
        $review->refresh();
        $this->assertSame(1, $review->webhook_failures);
        $this->assertStringContainsString('redirect', (string) $review->webhook_last_error);
    }

    public function test_five_failed_deliveries_pause_the_webhook_and_success_resets_it(): void
    {
        $status = 500;
        Http::fake(['*' => function () use (&$status) {
            return Http::response($status === 500 ? 'nope' : '', $status);
        }]);
        $review = $this->hookedReview();

        foreach (range(1, Review::WEBHOOK_PAUSE_AFTER) as $_) {
            $this->deliver($review->refresh());
        }

        $review->refresh();
        $this->assertNotNull($review->webhook_paused_at);
        $this->assertSame(['paused' => true, 'failures' => 5, 'last_error' => 'Answered 500.'], $review->toAgentPayload()['webhook']);

        // Paused: nothing more is sent.
        $status = 200;
        Http::assertSentCount(5);
        $this->deliver($review);
        Http::assertSentCount(5);

        // A fresh delivery that works clears the count.
        $review->forceFill(['webhook_paused_at' => null])->save();
        $this->deliver($review);
        $this->assertSame(0, $review->refresh()->webhook_failures);
    }

    public function test_a_next_pass_inherits_a_paused_webhook(): void
    {
        [$workspace] = $this->setUpWorkspace();
        $parent = app(ReviewService::class)->create($workspace, 'One', null, [$this->tinyPngDataUrl()], webhookUrl: 'https://ci.example.test/hooks/revisemy');
        $parent->forceFill(['status' => Review::STATUS_CHANGES_REQUESTED, 'webhook_failures' => 5, 'webhook_paused_at' => now()])->save();

        $child = app(ReviewService::class)->create($workspace, 'Two', null, [$this->tinyPngDataUrl()], parentPublicId: $parent->public_id);

        $this->assertNotNull($child->webhook_paused_at);
        $this->assertSame(5, $child->webhook_failures);
    }
}
