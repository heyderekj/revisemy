<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientCreditsException;
use App\Mcp\Prompts\DesignCheckupLoop;
use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\CancelSubscriptionTool;
use App\Mcp\Tools\CreateCheckoutTool;
use App\Mcp\Tools\CreatePortalTool;
use App\Mcp\Tools\CreateReviewTool;
use App\Mcp\Tools\GetBillingTool;
use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\CreditsService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

class BillingCreditsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function configurePolar(array $overrides = []): void
    {
        config([
            'billing.pricing_enabled' => true,
            'billing.polar.server' => 'sandbox',
            'billing.polar.access_token' => 'polar_oat_test',
            'billing.polar.webhook_secret' => 'whsec_'.base64_encode('test-secret'),
            'billing.polar.products.plus' => 'prod_plus',
            'billing.polar.products.credits_50' => 'prod_pack',
            ...$overrides,
        ]);
    }

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
        $this->assertSame(20, $workspace->credits_balance);
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

        $this->assertSame(19, $workspace->fresh()->credits_balance);
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
            ->assertJsonPath('next_action', 'wait_for_refill');
    }

    public function test_get_billing_returns_plan_summary(): void
    {
        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(GetBillingTool::class, [])
            ->assertHasNoErrors()
            ->assertStructuredContent(fn ($json) => $json
                ->where('plan', 'free')
                ->where('plan_name', 'Try')
                ->where('credits_remaining', 20)
                ->where('credits_grant', 20)
                ->where('credits_renew', true)
                ->whereType('credits_period_ends_at', 'string')
                ->where('pricing_enabled', false)
                ->where('checkout_available', false)
                ->where('burn_table.capture_url', 5)
                ->etc()
            );
    }

    public function test_try_refills_after_a_month(): void
    {
        $workspace = app(TryTokenService::class)->create()['workspace'];
        $workspace->forceFill([
            'credits_balance' => 5,
            'credits_period_start' => now()->subMonths(2),
        ])->save();

        $fresh = app(CreditsService::class)->ensurePeriod($workspace->fresh());

        $this->assertSame(20, (int) $fresh->credits_balance);
        $this->assertTrue($fresh->credits_period_start->greaterThan(now()->subDay()));
    }

    public function test_plus_does_not_refill_on_the_clock(): void
    {
        // Plus refills when Polar bills (order.paid), not a month after the last grant.
        $workspace = app(TryTokenService::class)->create()['workspace'];
        app(CreditsService::class)->activatePro($workspace, 'plus@example.com');
        $workspace->refresh()->forceFill([
            'credits_balance' => 3,
            'credits_period_start' => now()->subMonths(2),
        ])->save();

        $fresh = app(CreditsService::class)->ensurePeriod($workspace->fresh());

        $this->assertSame(3, (int) $fresh->credits_balance);
    }

    public function test_debit_spends_monthly_credits_before_purchased(): void
    {
        $credits = app(CreditsService::class);
        $workspace = app(TryTokenService::class)->create()['workspace'];
        $workspace->forceFill(['credits_balance' => 2, 'purchased_credits' => 50])->save();

        $split = $credits->debit($workspace, 5);

        $this->assertSame(['monthly' => 2, 'purchased' => 3], $split);
        $this->assertSame(0, (int) $workspace->fresh()->credits_balance);
        $this->assertSame(47, (int) $workspace->fresh()->purchased_credits);

        $credits->refund($workspace, $split);

        $this->assertSame(2, (int) $workspace->fresh()->credits_balance);
        $this->assertSame(50, (int) $workspace->fresh()->purchased_credits);
    }

    public function test_purchased_credits_survive_the_try_refill(): void
    {
        $workspace = app(TryTokenService::class)->create()['workspace'];
        $workspace->forceFill([
            'credits_balance' => 0,
            'purchased_credits' => 50,
            'credits_period_start' => now()->subMonths(2),
        ])->save();

        $this->assertSame(70, app(CreditsService::class)->remaining($workspace->fresh()));
    }

    public function test_purchased_credits_make_a_review_affordable(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        $result = app(TryTokenService::class)->create();
        $result['workspace']->forceFill(['credits_balance' => 0, 'purchased_credits' => 4])->save();

        ReviseMyServer::actingAs($result['user'])->tool(CreateReviewTool::class, [
            'title' => 'Paid with a pack',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertHasNoErrors();

        $this->assertSame(3, (int) $result['workspace']->fresh()->purchased_credits);
    }

    public function test_activate_free_does_not_grant_new_try_pack(): void
    {
        $workspace = app(TryTokenService::class)->create()['workspace'];
        app(CreditsService::class)->activatePro($workspace, 'plus@example.com');
        $workspace->refresh()->forceFill(['credits_balance' => 12])->save();

        $downgraded = app(CreditsService::class)->activateFree($workspace->fresh());

        $this->assertSame(Workspace::PLAN_FREE, $downgraded->plan);
        $this->assertSame(12, (int) $downgraded->credits_balance);
        $this->assertTrue(app(CreditsService::class)->planRenews($downgraded));
    }

    public function test_extend_try_command_adds_credits_and_extends_tokens(): void
    {
        $result = app(TryTokenService::class)->create();
        $workspace = $result['workspace'];
        $token = $result['user']->tokens()->first();
        $originalExpiry = $token->expires_at->copy();

        $this->artisan('revisemy:extend-try', [
            'workspace' => $workspace->public_id,
            '--credits' => 10,
            '--token-days' => 7,
        ])->assertSuccessful();

        $this->assertSame(30, (int) $workspace->fresh()->credits_balance);
        $this->assertTrue($token->fresh()->expires_at->equalTo($originalExpiry->addDays(7)));
    }

    public function test_extend_try_pack_grants_full_try_credits(): void
    {
        $result = app(TryTokenService::class)->create();
        $workspace = $result['workspace'];
        $workspace->forceFill(['credits_balance' => 2])->save();

        $this->artisan('revisemy:extend-try', [
            'workspace' => $workspace->public_id,
            '--pack' => true,
        ])->assertSuccessful();

        $this->assertSame(22, (int) $workspace->fresh()->credits_balance);
    }

    public function test_checkout_tools_are_not_advertised_while_pricing_is_paused(): void
    {
        $this->configurePolar(['billing.pricing_enabled' => false]);

        // Every registered tool's schema is loaded into the host's context for
        // the whole session, so the ones that can only ever fail stay off.
        $tools = $this->registeredTools();

        $this->assertNotContains(CreateCheckoutTool::class, $tools);
        $this->assertNotContains(CreatePortalTool::class, $tools);
        $this->assertNotContains(CancelSubscriptionTool::class, $tools);

        // get_billing stays — credits gate every create_review.
        $this->assertContains(GetBillingTool::class, $tools);

        // And the instructions say there is no checkout, so the model does not
        // go looking for one (or invent a payment link).
        $this->assertStringContainsString('no checkout tool', $this->serverInstructions());
    }

    public function test_checkout_tools_return_when_pricing_is_enabled(): void
    {
        $this->configurePolar();

        $tools = $this->registeredTools();

        $this->assertContains(CreateCheckoutTool::class, $tools);
        $this->assertContains(CreatePortalTool::class, $tools);
        $this->assertContains(CancelSubscriptionTool::class, $tools);
        $this->assertStringContainsString('create_checkout', $this->serverInstructions());
    }

    public function test_the_mcp_server_reports_the_configured_product_version(): void
    {
        config(['revisemy.version' => '9.9.9']);

        $this->assertSame('9.9.9', $this->serverProperty('version'));
    }

    /**
     * @return list<string>
     */
    protected function registeredTools(): array
    {
        return $this->serverProperty('tools');
    }

    protected function serverInstructions(): string
    {
        return $this->serverProperty('instructions');
    }

    protected function serverProperty(string $name): mixed
    {
        $server = new ReviseMyServer(new FakeTransporter);

        return (fn () => $this->{$name})->call($server);
    }

    public function test_create_checkout_errors_when_polar_not_configured(): void
    {
        $this->configurePolar(['billing.polar.access_token' => null]);

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

    public function test_billing_status_marks_checkout_unavailable_without_polar(): void
    {
        $this->configurePolar(['billing.polar.access_token' => null]);

        $workspace = app(TryTokenService::class)->create()['workspace'];
        $status = app(BillingService::class)->status($workspace);

        $this->assertFalse($status['polar_configured']);
        $this->assertFalse($status['pack_checkout_available']);
        $this->assertFalse($status['checkout_available']);
        $this->assertSame(20, $status['credits_remaining']);
    }

    public function test_create_checkout_returns_signed_checkout_url_when_configured(): void
    {
        $this->configurePolar();

        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(CreateCheckoutTool::class, [])
            ->assertHasNoErrors()
            ->assertSee([
                'share this link with the human now',
                'finish payment in the browser',
                '[![ReviseMy](',
            ])
            ->assertStructuredContent(fn ($json) => $json
                ->whereType('checkout_url', 'string')
                ->whereType('share_markdown', 'string')
                ->where('plan', 'pro')
                ->where('next_action', 'share_checkout_url')
                ->etc()
            );
    }

    public function test_create_checkout_sells_a_credit_pack(): void
    {
        $this->configurePolar();

        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(CreateCheckoutTool::class, ['product' => 'credits_50'])
            ->assertHasNoErrors()
            ->assertSee('Credit pack checkout ready')
            ->assertStructuredContent(fn ($json) => $json
                ->where('product', 'credits_50')
                ->where('price_usd', 5)
                ->where('credits_grant', 50)
                ->where('recurring', false)
                ->etc()
            );
    }

    public function test_plus_checkout_is_refused_on_plus_but_packs_are_not(): void
    {
        $this->configurePolar();

        $result = app(TryTokenService::class)->create();
        $result['workspace']->forceFill([
            'plan' => Workspace::PLAN_PRO,
            'polar_subscription_id' => 'sub_1',
            'polar_subscription_status' => 'active',
        ])->save();

        ReviseMyServer::actingAs($result['user'])->tool(CreateCheckoutTool::class, [])
            ->assertHasErrors(['already_subscribed']);

        ReviseMyServer::actingAs($result['user'])->tool(CreateCheckoutTool::class, ['product' => 'credits_50'])
            ->assertHasNoErrors();
    }

    public function test_signed_checkout_link_redirects_to_polar(): void
    {
        $this->configurePolar();
        Http::fake([
            'sandbox-api.polar.sh/v1/checkouts/' => Http::response([
                'id' => 'chk_1',
                'url' => 'https://sandbox.polar.sh/checkout/chk_1',
            ], 201),
        ]);

        $workspace = app(TryTokenService::class)->create()['workspace'];
        $url = app(BillingService::class)->createCheckoutUrl($workspace, 'credits_50');

        $this->get($url)->assertRedirect('https://sandbox.polar.sh/checkout/chk_1');

        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox-api.polar.sh/v1/checkouts/'
            && $request['products'] === ['prod_pack']
            && $request['external_customer_id'] === $workspace->public_id
            && $request['metadata']['workspace_public_id'] === $workspace->public_id
            && str_contains($request['success_url'], '{CHECKOUT_ID}')
            && $request->hasHeader('Authorization', 'Bearer polar_oat_test'));
    }

    public function test_checkout_link_must_be_signed(): void
    {
        $this->configurePolar();
        $workspace = app(TryTokenService::class)->create()['workspace'];

        $this->get('/billing/checkout/'.$workspace->public_id.'?product=plus')->assertForbidden();
    }

    public function test_success_page_never_grants_credits(): void
    {
        $this->configurePolar();
        $workspace = app(TryTokenService::class)->create()['workspace'];
        $workspace->forceFill(['credits_balance' => 1])->save();

        $this->get('/billing/success?checkout_id=chk_1&workspace='.$workspace->public_id)
            ->assertOk()
            ->assertSee('Payment received');

        $this->assertSame(1, (int) $workspace->fresh()->credits_balance);
        $this->assertSame(Workspace::PLAN_FREE, $workspace->fresh()->plan);
    }

    public function test_api_billing_endpoint(): void
    {
        $result = app(TryTokenService::class)->create();

        $this->withToken($result['token'])
            ->getJson('/api/billing')
            ->assertOk()
            ->assertJsonPath('plan', 'free')
            ->assertJsonPath('plan_name', 'Try')
            ->assertJsonPath('credits_grant', 20)
            ->assertJsonPath('credits_renew', true)
            ->assertJsonPath('pricing_enabled', false)
            ->assertJsonPath('checkout_available', false)
            ->assertJson(fn ($json) => $json
                ->whereType('credits_period_ends_at', 'string')
                ->etc()
            );
    }

    public function test_upgrade_page_is_hidden_while_pricing_is_paused(): void
    {
        config(['billing.pricing_enabled' => false]);

        $this->get('/upgrade')->assertNotFound();
    }

    public function test_upgrade_page_shows_plus_and_the_credit_pack(): void
    {
        $this->configurePolar();

        $this->get('/upgrade')
            ->assertOk()
            ->assertSee('$9', false)
            ->assertSee('Credit pack')
            ->assertSee('credits_50')
            ->assertSee('Polar');
    }

    public function test_checkup_prompt_offers_plus_and_packs_when_pricing_is_on(): void
    {
        $this->configurePolar();

        ReviseMyServer::prompt(DesignCheckupLoop::class, [])
            ->assertSee(['create_checkout', 'credits_50', 'Plus ($9/mo, 100 credits/mo)']);
    }

    public function test_checkup_prompt_says_checkout_is_off_when_paused(): void
    {
        config(['billing.pricing_enabled' => false]);

        ReviseMyServer::prompt(DesignCheckupLoop::class, [])
            ->assertSee('Paid checkout is off on this server')
            ->assertDontSee('credits_50');
    }

    public function test_sitemap_lists_pricing_only_when_enabled(): void
    {
        config(['billing.pricing_enabled' => false]);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/upgrade');

        $this->configurePolar();
        $this->get('/sitemap.xml')->assertOk()->assertSee('/upgrade');
    }

    public function test_cancel_subscription_requires_confirm(): void
    {
        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(CancelSubscriptionTool::class, [])
            ->assertHasErrors();

        ReviseMyServer::actingAs($result['user'])->tool(CancelSubscriptionTool::class, [
            'confirm' => false,
        ])->assertHasErrors();
    }

    public function test_cancel_subscription_errors_when_not_on_plus(): void
    {
        $result = app(TryTokenService::class)->create();

        ReviseMyServer::actingAs($result['user'])->tool(CancelSubscriptionTool::class, [
            'confirm' => true,
        ])->assertHasErrors();
    }
}
