<?php

namespace Tests\Feature;

use App\Mcp\Resources\ReviewApp;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\AddMarkTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\DecideReviewTool;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\VerifyMarkTool;
use App\Models\Annotation;
use App\Models\Review;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class McpAppTest extends TestCase
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
     * Create an isolated workspace user + a review (optionally already decided),
     * and return [User, Review]. Built directly via services so each call is a
     * fully independent workspace — no shared HTTP auth state between calls.
     */
    protected function setUpReview(string $status = Review::STATUS_PENDING): array
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $try = app(TryTokenService::class)->create();
        $workspace = $try['workspace'];
        $user = $try['user'];

        $review = app(ReviewService::class)->create(
            $workspace,
            'Inline pass',
            'Check the CTA',
            [$this->tinyPngDataUrl()],
        );

        if ($status !== Review::STATUS_PENDING) {
            $review->update(['status' => $status, 'decision_at' => now()]);
        }

        return [$user, $review->fresh()];
    }

    public function test_render_tools_declare_the_ui_resource(): void
    {
        foreach ([GetReviewTool::class, CreateReviewTool::class] as $tool) {
            $meta = app($tool)->toArray()['_meta'] ?? [];
            $this->assertSame('ui://revisemy/review-app', $meta['ui']['resourceUri'] ?? null, $tool);
        }
    }

    public function test_app_only_tools_are_hidden_from_the_model(): void
    {
        foreach ([AddMarkTool::class, DecideReviewTool::class, VerifyMarkTool::class] as $tool) {
            $meta = app($tool)->toArray()['_meta'] ?? [];
            $this->assertSame('ui://revisemy/review-app', $meta['ui']['resourceUri'] ?? null, $tool);
            $this->assertSame(['app'], $meta['ui']['visibility'] ?? null, $tool);
        }
    }

    public function test_resource_serves_the_app_html(): void
    {
        $resource = new ReviewApp;

        $this->assertSame('text/html;profile=mcp-app', $resource->mimeType());
        $this->assertSame('ui://revisemy/review-app', $resource->uri());

        $meta = $resource->resolvedAppMeta();
        $this->assertArrayHasKey('csp', $meta);
        // Alpine + Tailwind CDNs (Library enum) and the review page's webfont host.
        $this->assertContains('https://cdn.jsdelivr.net', $meta['csp']['resourceDomains']);
        $this->assertContains('https://cdn.tailwindcss.com', $meta['csp']['resourceDomains']);
        $this->assertContains('https://fonts.bunny.net', $meta['csp']['resourceDomains']);

        ReviseMyServer::resource(ReviewApp::class)
            ->assertOk()
            ->assertSee(['reviewApp', 'mcpBridge', 'add_mark']);
    }

    public function test_add_mark_creates_a_human_mark_and_returns_fresh_payload(): void
    {
        [$user, $review] = $this->setUpReview();
        $shot = $review->screenshots()->firstOrFail();

        ReviseMyServer::actingAs($user)->tool(AddMarkTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
            'x' => 0.9,
            'y' => 0.25,
            'severity' => 'must-fix',
            'body' => 'Tighten the CTA spacing.',
        ])->assertHasNoErrors()->assertStructuredContent(
            fn ($json) => $json->where('loop.must_fix_count', 1)->etc()
        );

        $mark = Annotation::query()->firstOrFail();
        $this->assertSame(1, $mark->number);
        $this->assertSame('must-fix', $mark->severity);
        $this->assertEqualsWithDelta(0.9, (float) $mark->x, 0.0001);
        $this->assertSame(Annotation::STATUS_OPEN, $mark->status);

        // Coordinates are clamped into [0, 1].
        ReviseMyServer::actingAs($user)->tool(AddMarkTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
            'x' => 1,
            'y' => 0,
            'severity' => 'nit',
            'body' => 'Edge mark.',
        ])->assertHasNoErrors();

        $edge = Annotation::query()->where('number', 2)->firstOrFail();
        $this->assertEqualsWithDelta(1.0, (float) $edge->x, 0.0001);
    }

    public function test_add_mark_rejects_a_review_from_another_workspace(): void
    {
        [$userA, $reviewA] = $this->setUpReview();
        $shotA = $reviewA->screenshots()->firstOrFail();
        [$userB] = $this->setUpReview();

        ReviseMyServer::actingAs($userB)->tool(AddMarkTool::class, [
            'review_id' => $reviewA->public_id,
            'screenshot_id' => $shotA->id,
            'x' => 0.5,
            'y' => 0.5,
            'severity' => 'nit',
            'body' => 'Not mine to mark.',
        ])->assertHasErrors();

        $this->assertSame(0, Annotation::query()->count());
    }

    public function test_decide_review_approve_verifies_resolved_marks_including_parent(): void
    {
        [$user, $parent] = $this->setUpReview(Review::STATUS_CHANGES_REQUESTED);
        $parentShot = $parent->screenshots()->firstOrFail();
        $parentMark = $parentShot->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Parent fix',
            'number' => 1, 'status' => Annotation::STATUS_RESOLVED, 'resolved_at' => now(),
        ]);

        // Next pass built on the parent, also with a resolved mark.
        $child = Review::query()->create([
            'workspace_id' => $parent->workspace_id,
            'parent_id' => $parent->id,
            'pass' => 2,
            'title' => 'Pass 2',
            'type' => $parent->type,
            'status' => Review::STATUS_PENDING,
            'token' => str()->random(40),
            'share_token' => str()->random(40),
            'expires_at' => now()->addDays(7),
        ]);
        $childShot = $child->screenshots()->create([
            'path' => $parentShot->path, 'disk' => $parentShot->disk,
            'width' => 1, 'height' => 1, 'sort_order' => 0,
        ]);
        $childMark = $childShot->annotations()->create([
            'x' => 0.4, 'y' => 0.4, 'severity' => 'must-fix', 'body' => 'Child fix',
            'number' => 1, 'status' => Annotation::STATUS_RESOLVED, 'resolved_at' => now(),
        ]);

        ReviseMyServer::actingAs($user)->tool(DecideReviewTool::class, [
            'review_id' => $child->public_id,
            'decision' => 'approved',
            'note' => 'Looks great.',
        ])->assertHasNoErrors();

        $this->assertSame(Review::STATUS_APPROVED, $child->fresh()->status);
        $this->assertSame(Annotation::STATUS_VERIFIED, $childMark->fresh()->status);
        $this->assertSame(Annotation::STATUS_VERIFIED, $parentMark->fresh()->status);
    }

    public function test_verify_mark_verifies_then_reopens(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_CHANGES_REQUESTED);
        $shot = $review->screenshots()->firstOrFail();
        $mark = $shot->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix',
            'number' => 1, 'status' => Annotation::STATUS_RESOLVED, 'resolved_at' => now(),
        ]);

        ReviseMyServer::actingAs($user)->tool(VerifyMarkTool::class, [
            'review_id' => $review->public_id, 'mark_id' => $mark->id, 'action' => 'verify',
        ])->assertHasNoErrors();
        $this->assertSame(Annotation::STATUS_VERIFIED, $mark->fresh()->status);

        ReviseMyServer::actingAs($user)->tool(VerifyMarkTool::class, [
            'review_id' => $review->public_id, 'mark_id' => $mark->id, 'action' => 'reopen',
        ])->assertHasNoErrors();
        $this->assertSame(Annotation::STATUS_OPEN, $mark->fresh()->status);
    }

    public function test_add_mark_refuses_a_closed_review(): void
    {
        [$user, $review] = $this->setUpReview(Review::STATUS_APPROVED);
        $shot = $review->screenshots()->firstOrFail();

        ReviseMyServer::actingAs($user)->tool(AddMarkTool::class, [
            'review_id' => $review->public_id,
            'screenshot_id' => $shot->id,
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Too late.',
        ])->assertHasErrors();
    }
}
