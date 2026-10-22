<?php

namespace Tests\Feature;

use App\Events\ReviewHandedOff;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\GetReviewTool;
use App\Mcp\Tools\LookAtMarksTool;
use App\Mcp\Tools\ResolveMarksTool;
use App\Models\Annotation;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HandoffByLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Queue::fake();
        config(['filesystems.revisemy_disk' => 'public']);
    }

    protected function tinyPngDataUrl(): string
    {
        return 'data:image/png;base64,'.base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));
    }

    /** A review someone started at /new, with one mark on it. */
    protected function webReview(?string $origin = Review::ORIGIN_WEB): Review
    {
        $owner = app(TryTokenService::class)->createWorkspaceUser();
        $review = app(ReviewService::class)->create(
            $owner->workspace, 'Pricing page', null, [$this->tinyPngDataUrl()], origin: $origin,
        );
        $review->screenshots->first()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Tighten the table', 'number' => 1,
        ]);

        return $review->fresh();
    }

    protected function agent(): User
    {
        return app(TryTokenService::class)->create()['user'];
    }

    public function test_an_agent_takes_a_web_review_by_its_link(): void
    {
        Event::fake([ReviewHandedOff::class]);
        $review = $this->webReview();
        $agent = $this->agent();

        ReviseMyServer::actingAs($agent)->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])
            ->assertHasNoErrors()
            ->assertStructuredContent(fn ($json) => $json->where('id', $review->public_id)->etc());

        $review->refresh();
        $this->assertSame($agent->workspace_id, $review->workspace_id);
        $this->assertNotNull($review->handed_off_at);
        Event::assertDispatched(ReviewHandedOff::class);

        // From here every tool works by id, as if the agent had made it.
        $review->update(['status' => Review::STATUS_CHANGES_REQUESTED]);
        $mark = Annotation::query()->sole();
        ReviseMyServer::actingAs($agent)->tool(ResolveMarksTool::class, [
            'id' => $review->public_id,
            'marks' => [['id' => $mark->id, 'status' => 'resolved', 'note' => 'Tightened the rows.']],
        ])->assertHasNoErrors();

        $this->assertSame(Annotation::STATUS_RESOLVED, $mark->fresh()->status);
    }

    public function test_the_board_link_and_a_bare_token_work_too(): void
    {
        $review = $this->webReview();

        ReviseMyServer::actingAs($this->agent())->tool(LookAtMarksTool::class, ['id' => $review->boardUrl()])
            ->assertHasNoErrors();
        $this->assertNotNull($review->fresh()->handed_off_at);

        $other = $this->webReview();
        ReviseMyServer::actingAs($this->agent())->tool(GetReviewTool::class, ['id' => $other->token])
            ->assertHasNoErrors();
        $this->assertNotNull($other->fresh()->handed_off_at);
    }

    public function test_a_second_agent_cannot_take_it_again(): void
    {
        $review = $this->webReview();
        $first = $this->agent();

        ReviseMyServer::actingAs($first)->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])->assertHasNoErrors();
        ReviseMyServer::actingAs($this->agent())->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])
            ->assertHasErrors(['the first agent to open it keeps it']);

        $this->assertSame($first->workspace_id, $review->fresh()->workspace_id);

        // The agent that has it can keep using the link.
        ReviseMyServer::actingAs($first)->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])->assertHasNoErrors();
    }

    public function test_a_guest_link_never_hands_a_review_over(): void
    {
        $review = $this->webReview();

        ReviseMyServer::actingAs($this->agent())->tool(GetReviewTool::class, ['id' => $review->shareUrl()])
            ->assertHasErrors();

        $this->assertNull($review->fresh()->handed_off_at);
    }

    public function test_a_review_an_agent_made_never_moves(): void
    {
        $review = $this->webReview(origin: null);
        $workspaceId = $review->workspace_id;

        ReviseMyServer::actingAs($this->agent())->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])
            ->assertHasErrors();

        $this->assertSame($workspaceId, $review->fresh()->workspace_id);
    }

    public function test_a_link_on_another_site_is_ignored(): void
    {
        $review = $this->webReview();

        ReviseMyServer::actingAs($this->agent())->tool(GetReviewTool::class, ['id' => 'https://elsewhere.test/r/'.$review->token])
            ->assertHasErrors();

        $this->assertNull($review->fresh()->handed_off_at);
    }

    public function test_the_browser_that_started_it_is_already_home(): void
    {
        $review = $this->webReview();
        $sameWorkspace = User::query()->where('workspace_id', $review->workspace_id)->sole();

        ReviseMyServer::actingAs($sameWorkspace)->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])
            ->assertHasNoErrors();

        // Nothing moved; a different agent can still take it.
        $this->assertNull($review->fresh()->handed_off_at);
    }

    public function test_later_passes_move_with_it(): void
    {
        $review = $this->webReview();
        $pass2 = app(ReviewService::class)->create($review->workspace, 'Pricing page', null, [$this->tinyPngDataUrl()], parentPublicId: $review->public_id);
        $agent = $this->agent();

        ReviseMyServer::actingAs($agent)->tool(GetReviewTool::class, ['id' => $review->reviewUrl()])->assertHasNoErrors();

        $this->assertSame($agent->workspace_id, $pass2->fresh()->workspace_id);
    }

    public function test_rest_takes_it_by_the_token_from_the_link(): void
    {
        $review = $this->webReview();
        $token = app(TryTokenService::class)->create()['token'];

        $this->withToken($token)->getJson('/api/reviews/'.$review->token)
            ->assertOk()
            ->assertJsonPath('id', $review->public_id);

        $this->assertNotNull($review->fresh()->handed_off_at);

        $other = app(TryTokenService::class)->create()['token'];
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/reviews/'.$review->token)->assertNotFound();
    }
}
