<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientCreditsException;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\CreateCheckoutTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\GetBillingTool;
use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\CreditsService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $binary = hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        );

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    public function test_try_token_workspace_starts_with_free_credit_grant(): void
    {
        $result = app(TryTokenService::class)->create();
        $workspace = $result['workspace']->fresh();

        $this->assertSame(Workspace::PLAN_FREE, $workspace->plan);
        $this->assertSame(30, $workspace->credits_balance);
        $this->assertNotNull($workspace->credits_period_start);
    }

    public function test_create_review_debits_one_credit_for_images(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $result = app(TryTokenService::class)->create();
        $user = $result['user'];
        $workspace = $result['workspace']->fresh();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Credit debit',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertHasNoErrors();

        $this->assertSame(29, $workspace->fresh()->credits_balance);
    }

    public function test_insufficient_credits_blocks_create_review(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $result = app(TryTokenService::class)->create();
        $user = $result['user'];
        $workspace = $result['workspace'];
        $workspace->forceFill(['credits_balance' => 0])->save();

        ReviseMyServer::actingAs($user)->tool(CreateReviewTool::class, [
            'title' => 'Should fail',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertHasErrors();

        $this->assertSame(0, $workspace->fresh()->credits_balance);
    }

    public function test_api_create_review_returns_402_when_out_of_credits(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $result = app(TryTokenService::class)->create();
        $result['workspace']->forceFill(['credits_balance' => 0])->save();

        $this->withToken($result['token'])
            ->postJson('/api/reviews', [
                'title' => 'No credits',
                'images' => [$this->tinyPngDataUrl()],
            ])
            ->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credits')
            ->assertJsonPath('next_action', 'upgrade');
    }

    public function test_get_billing_returns_plan_summary(): void
    {
        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(GetBillingTool::class, [])
            ->assertHasNoErrors()
            ->assertStructuredContent(fn ($json) => $json
                ->where('plan', 'free')
                ->where('credits_remaining', 30)
                ->where('credits_grant', 30)
                ->where('burn_table.capture_url', 5)
                ->etc()
            );
    }

    public function test_create_checkout_errors_when_stripe_not_configured(): void
    {
        config([
            'cashier.secret' => null,
            'billing.plans.pro.stripe_price' => null,
        ]);

        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(CreateCheckoutTool::class, [])
            ->assertHasErrors();
    }

    public function test_activate_pro_grants_pro_credits_and_retention(): void
    {
        $workspace = app(TryTokenService::class)->create()['workspace'];
        $credits = app(CreditsService::class);

        $credits->activatePro($workspace, 'founder@example.com');
        $workspace->refresh();

        $this->assertSame(Workspace::PLAN_PRO, $workspace->plan);
        $this->assertSame(100, $workspace->credits_balance);
        $this->assertSame('founder@example.com', $workspace->billing_email);
        $this->assertSame(90, $workspace->reviewRetentionDays());
    }

    public function test_credits_service_throws_typed_exception(): void
    {
        $workspace = app(TryTokenService::class)->create()['workspace'];
        $workspace->forceFill(['credits_balance' => 2])->save();

        $this->expectException(InsufficientCreditsException::class);

        app(CreditsService::class)->assertAffordable($workspace->fresh(), 5);
    }

    public function test_billing_status_marks_checkout_unavailable_without_stripe(): void
    {
        config([
            'cashier.secret' => null,
            'billing.plans.pro.stripe_price' => null,
        ]);

        $workspace = app(TryTokenService::class)->create()['workspace'];
        $status = app(BillingService::class)->status($workspace);

        $this->assertFalse($status['stripe_configured']);
        $this->assertFalse($status['checkout_available']);
        $this->assertSame(30, $status['credits_remaining']);
    }

    public function test_api_billing_endpoint(): void
    {
        $result = app(TryTokenService::class)->create();

        $this->withToken($result['token'])
            ->getJson('/api/billing')
            ->assertOk()
            ->assertJsonPath('plan', 'free')
            ->assertJsonPath('credits_grant', 30);
    }
}
