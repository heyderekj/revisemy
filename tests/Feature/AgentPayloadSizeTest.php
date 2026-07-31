<?php

namespace Tests\Feature;

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * next_action tells agents to poll get_review, so payload size is a running
 * cost, not a one-off. Every mark already appears under its screenshot, in
 * work_packets.pins, and in a severity bucket — these tests pin down what each
 * copy is allowed to carry so the payload cannot quietly re-inflate.
 */
class AgentPayloadSizeTest extends TestCase
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
     * A representative review: two screenshots, six marks, comments on one.
     */
    protected function setUpReview(): Review
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Payload size',
            'images' => [$this->tinyPngDataUrl(), $this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shots = $review->screenshots()->get();

        $number = 1;

        foreach ($shots as $shot) {
            foreach (['must-fix', 'nit', 'question'] as $severity) {
                $shot->annotations()->create([
                    'x' => 0.5,
                    'y' => 0.5,
                    'area' => ['x' => 0.4, 'y' => 0.4, 'w' => 0.2, 'h' => 0.2],
                    'severity' => $severity,
                    'body' => 'The spacing under the heading is inconsistent with the rest of the page.',
                    'number' => $number++,
                ]);
            }
        }

        $shots->first()->annotations()->first()->comments()->create([
            'author' => 'Derek',
            'from_owner' => true,
            'body' => 'Matching the 24px rhythm used everywhere else is fine here.',
        ]);

        return $review->fresh();
    }

    public function test_signed_screenshot_urls_appear_once_per_screenshot(): void
    {
        $payload = $this->setUpReview()->toAgentPayload();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // /shots/{id} is the signed screenshot route. Two source screenshots
        // means two URLs — not one per mark per copy.
        $this->assertSame(
            2,
            substr_count($json, '/shots/'),
            'A signed screenshot URL leaked into the per-mark payload copies.'
        );
    }

    public function test_focus_preview_carries_geometry_but_no_css(): void
    {
        $payload = $this->setUpReview()->toAgentPayload();
        $pin = $payload['screenshots'][0]['pins'][0];

        $this->assertArrayHasKey('focus_preview', $pin);
        $this->assertArrayHasKey('window', $pin['focus_preview']);
        $this->assertArrayHasKey('ratio', $pin['focus_preview']);
        $this->assertArrayNotHasKey('bg_style', $pin['focus_preview']);
    }

    public function test_comment_threads_reach_work_packets_but_not_the_severity_buckets(): void
    {
        $payload = $this->setUpReview()->toAgentPayload();

        // The payload's own guidance tells agents to read comments on
        // work_packets.pins, so the thread has to survive there.
        $packet = collect($payload['work_packets']['pins'])->firstWhere('comment_count', 1);
        $this->assertNotNull($packet);
        $this->assertCount(1, $packet['comments']);

        // Buckets are triage views of the same marks — id plus count is enough.
        $bucketed = collect($payload['work_packets']['must_fix'])->firstWhere('id', $packet['id']);
        $this->assertNotNull($bucketed);
        $this->assertArrayNotHasKey('comments', $bucketed);
        $this->assertSame(1, $bucketed['comment_count']);
    }

    public function test_the_redundant_annotations_alias_is_gone(): void
    {
        $payload = $this->setUpReview()->toAgentPayload();

        $this->assertArrayHasKey('pins', $payload['screenshots'][0]);
        $this->assertArrayNotHasKey('annotations', $payload['screenshots'][0]);
    }

    public function test_payload_stays_under_a_reasonable_ceiling(): void
    {
        $payload = $this->setUpReview()->toAgentPayload();
        $bytes = strlen((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

        // 2 screenshots / 6 marks measured at ~14KB after the trim (~35KB
        // before). The ceiling is deliberately loose — it catches a structural
        // regression, not ordinary copy changes.
        $this->assertLessThan(
            22_000,
            $bytes,
            "get_review payload grew to {$bytes} bytes for 2 screenshots and 6 marks."
        );
    }
}
