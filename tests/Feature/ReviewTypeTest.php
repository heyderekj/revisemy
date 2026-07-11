<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use App\Services\SecondOpinionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    protected function createReview(array $overrides = []): array
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');

        $response = $this->withToken($token)->postJson('/api/reviews', array_merge([
            'title' => 'Type test',
            'images' => [$this->tinyPngDataUrl()],
        ], $overrides));

        return [$token, $response];
    }

    public function test_review_defaults_to_ui_type(): void
    {
        [, $response] = $this->createReview();

        $response->assertCreated()->assertJsonPath('type', 'ui');
        $this->assertSame('ui', Review::query()->firstOrFail()->type);
    }

    public function test_review_accepts_each_type_and_exposes_it_in_payload(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');

        foreach (Review::types() as $type) {
            $this->withToken($token)->postJson('/api/reviews', [
                'title' => "A {$type} review",
                'type' => $type,
                'images' => [$this->tinyPngDataUrl()],
            ])->assertCreated()->assertJsonPath('type', $type);
        }
    }

    public function test_invalid_type_is_rejected(): void
    {
        [, $response] = $this->createReview(['type' => 'poster']);

        $response->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_next_pass_inherits_parent_type(): void
    {
        [$token, $response] = $this->createReview(['type' => 'email']);
        $parentId = $response->json('id');

        $parent = Review::query()->firstOrFail();
        $parent->update(['status' => Review::STATUS_CHANGES_REQUESTED]);

        $child = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Pass 2',
            'parent_id' => $parentId,
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        $child->assertJsonPath('type', 'email')->assertJsonPath('pass', 2);
    }

    public function test_checklist_findings_differ_by_type_and_carry_no_area(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public', 'revisemy.openai.api_key' => null]);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');
        $service = app(SecondOpinionService::class);
        $bodiesByType = [];

        foreach (Review::types() as $type) {
            $this->withToken($token)->postJson('/api/reviews', [
                'title' => "A {$type} review",
                'type' => $type,
                'images' => [$this->tinyPngDataUrl()],
            ])->assertCreated();

            $screenshot = Review::query()->where('type', $type)->firstOrFail()
                ->screenshots()->firstOrFail();

            $service->generate($screenshot);

            $findings = $screenshot->fresh('findings')->findings;
            $this->assertNotEmpty($findings, "No checklist findings for {$type}");

            foreach ($findings as $finding) {
                $this->assertSame(Finding::SOURCE_CHECKLIST, $finding->source);
                $this->assertNull($finding->area, "Checklist finding for {$type} should not carry an area: {$finding->body}");
            }

            $bodiesByType[$type] = $findings->pluck('body')->sort()->values()->all();
        }

        $this->assertNotSame($bodiesByType['ui'], $bodiesByType['email']);
        $this->assertNotSame($bodiesByType['ui'], $bodiesByType['presentation']);
        $this->assertNotSame($bodiesByType['ui'], $bodiesByType['website']);

        $this->assertSame(
            Screenshot::OPINION_READY,
            Review::query()->where('type', 'ui')->firstOrFail()->screenshots()->firstOrFail()->second_opinion_status,
        );
    }
}
