<?php

namespace Tests\Feature;

use App\Models\Annotation;
use App\Models\Finding;
use App\Models\Review;
use App\Services\MarkLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AgentFeedbackLoopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);
    }

    public function test_work_packets_include_comments_suggested_copy_and_question_answer(): void
    {
        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Packets',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $mark = app(MarkLifecycleService::class)->createMark(
            $shot,
            0.5,
            0.5,
            null,
            Annotation::SEVERITY_QUESTION,
            'Should the CTA say Start or Begin?',
            ['suggested_copy' => 'Start free'],
        );

        $mark->comments()->create([
            'author' => 'Owner',
            'from_owner' => true,
            'body' => 'Prefer Start free.',
        ]);

        app(MarkLifecycleService::class)->answerQuestion($mark, 'Use Start free.');

        $payload = $review->fresh()->toAgentPayload();
        $pin = $payload['work_packets']['pins'][0];

        $this->assertSame('Start free', $pin['suggested_copy']);
        $this->assertSame('Use Start free.', $pin['question_answer']);
        $this->assertSame(1, $pin['comment_count']);
        $this->assertSame('Prefer Start free.', $pin['comments'][0]['body']);
        $this->assertSame('human', $pin['source']);
        $this->assertArrayHasKey('pass_ledger', $payload);
        $this->assertSame(1, $payload['pass_ledger'][0]['pass']);
        $this->assertTrue($payload['pass_ledger'][0]['is_current']);
    }

    public function test_pass_ledger_spans_parent_chain(): void
    {
        $token = $this->postJson('/api/try-token')->json('token');

        $firstId = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Pass 1',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $first = Review::query()->where('public_id', $firstId)->firstOrFail();
        $first->update([
            'status' => Review::STATUS_CHANGES_REQUESTED,
            'decision_note' => 'Fix the hero',
            'decision_at' => now(),
        ]);

        $secondId = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Pass 2',
            'images' => [$this->tinyPngDataUrl()],
            'parent_id' => $firstId,
        ])->json('id');

        $second = Review::query()->where('public_id', $secondId)->firstOrFail();
        $ledger = $second->toAgentPayload()['pass_ledger'];

        $this->assertCount(2, $ledger);
        $this->assertSame(1, $ledger[0]['pass']);
        $this->assertSame('Fix the hero', $ledger[0]['decision_note']);
        $this->assertFalse($ledger[0]['is_current']);
        $this->assertSame(2, $ledger[1]['pass']);
        $this->assertTrue($ledger[1]['is_current']);
    }

    public function test_list_reviews_returns_enriched_summaries(): void
    {
        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Summary',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $open = app(MarkLifecycleService::class)->createMark(
            $shot,
            0.2,
            0.2,
            null,
            Annotation::SEVERITY_MUST_FIX,
            'Fix contrast',
        );

        app(MarkLifecycleService::class)->createMark(
            $shot,
            0.8,
            0.8,
            null,
            Annotation::SEVERITY_NIT,
            'Tighten gap',
        );

        app(MarkLifecycleService::class)->applyAgentUpdates($review->workspace, [
            ['id' => $open->id, 'status' => Annotation::STATUS_RESOLVED, 'note' => 'Bumped contrast'],
        ]);

        $response = $this->withToken($token)->getJson('/api/reviews');

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('reviews.0.id', $id)
            ->assertJsonPath('reviews.0.loop.must_fix_count', 0)
            ->assertJsonPath('reviews.0.loop.nit_count', 1)
            ->assertJsonPath('reviews.0.loop.outstanding_count', 1)
            ->assertJsonPath('reviews.0.loop.awaiting_verification_count', 1)
            ->assertJsonPath('reviews.0.next_action', 'wait_for_human')
            ->assertJsonMissingPath('reviews.0.work_packets');
    }

    public function test_accepting_finding_records_provenance_and_severity_override(): void
    {
        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Triage',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $this->withToken($token)->postJson('/api/reviews/'.$id.'/findings', [
            'findings' => [
                [
                    'severity' => 'a11y',
                    'body' => 'Missing label',
                    'screenshot_index' => 0,
                ],
                [
                    'severity' => 'polish',
                    'body' => 'Soft shadow',
                    'screenshot_index' => 0,
                ],
            ],
        ])->assertCreated();

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $a11y = $review->screenshots()->firstOrFail()->findings()->where('severity', 'a11y')->firstOrFail();
        $polish = $review->screenshots()->firstOrFail()->findings()->where('severity', 'polish')->firstOrFail();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('acceptFinding', $a11y->id, Annotation::SEVERITY_NIT)
            ->call('dismissOpenFindings', 'second')
            ->assertOk();

        $payload = $review->fresh()->toAgentPayload();

        $this->assertCount(1, $payload['work_packets']['pins']);
        $this->assertSame(Annotation::SEVERITY_NIT, $payload['work_packets']['pins'][0]['severity']);
        $this->assertSame(Annotation::SOURCE_AGENT, $payload['work_packets']['pins'][0]['source']);
        $this->assertSame($a11y->id, $payload['work_packets']['pins'][0]['promoted_from_finding_id']);
        $this->assertSame(Finding::STATUS_DISMISSED, $polish->fresh()->status);
    }

    public function test_verify_all_resolved_and_batch_accept_guest(): void
    {
        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Verify',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $mark = app(MarkLifecycleService::class)->createMark(
            $shot,
            0.4,
            0.4,
            null,
            Annotation::SEVERITY_MUST_FIX,
            'Fix padding',
        );

        app(MarkLifecycleService::class)->applyAgentUpdates($review->workspace, [
            ['id' => $mark->id, 'status' => Annotation::STATUS_RESOLVED, 'note' => 'Done'],
        ]);

        $shot->findings()->create([
            'source' => Finding::SOURCE_GUEST,
            'author' => 'Alex',
            'severity' => Annotation::SEVERITY_MUST_FIX,
            'body' => 'Guest idea',
            'x' => 0.1,
            'y' => 0.1,
            'status' => Finding::STATUS_OPEN,
        ]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('verifyAllResolved')
            ->call('acceptOpenFindings', 'guest')
            ->assertOk();

        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);

        $payload = $review->fresh()->toAgentPayload();
        $guestPin = collect($payload['work_packets']['pins'])->firstWhere('body', 'Guest idea');

        $this->assertNotNull($guestPin);
        $this->assertSame(Annotation::SOURCE_GUEST, $guestPin['source']);
    }

    public function test_recent_reviews_page_loads_for_try_token(): void
    {
        $token = $this->postJson('/api/try-token')->json('token');
        $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Memory',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        Livewire::test('recent-reviews')
            ->set('tryToken', $token)
            ->call('loadReviews')
            ->assertOk()
            ->assertSet('loaded', true)
            ->assertCount('reviews', 1);
    }

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }
}
