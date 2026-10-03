<?php

namespace Tests\Feature;

use App\Mcp\Servers\ReviseMyServer;
use App\Mcp\Tools\CancelSubscriptionTool;
use App\Models\BillingOrder;
use App\Models\Workspace;
use App\Services\TryTokenService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PolarWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected const SECRET_KEY = 'test-signing-key';

    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.pricing_enabled' => true,
            'billing.polar.server' => 'sandbox',
            'billing.polar.access_token' => 'polar_oat_test',
            'billing.polar.webhook_secret' => 'whsec_'.base64_encode(self::SECRET_KEY),
            'billing.polar.products.plus' => 'prod_plus',
            'billing.polar.products.credits_50' => 'prod_pack',
        ]);

        $this->workspace = app(TryTokenService::class)->create()['workspace']->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function deliver(string $type, array $data, ?string $key = null, ?int $timestamp = null): TestResponse
    {
        $body = json_encode(['type' => $type, 'timestamp' => now()->toIso8601String(), 'data' => $data]);
        $id = 'msg_'.bin2hex(random_bytes(6));
        $timestamp ??= time();
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key ?? self::SECRET_KEY, true));

        return $this->call('POST', '/polar/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => "v1,{$signature}",
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function order(string $id, string $productId, string $reason = 'purchase', array $extra = []): array
    {
        return [
            'id' => $id,
            'status' => 'paid',
            'billing_reason' => $reason,
            'product_id' => $productId,
            'subscription_id' => $productId === 'prod_plus' ? 'sub_1' : null,
            'customer_id' => 'cus_1',
            'total_amount' => $productId === 'prod_plus' ? 900 : 500,
            'customer' => ['id' => 'cus_1', 'external_id' => $this->workspace->public_id, 'email' => 'buyer@example.com'],
            'metadata' => ['workspace_public_id' => $this->workspace->public_id],
            ...$extra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function subscription(string $status, bool $cancelAtPeriodEnd = false): array
    {
        return [
            'id' => 'sub_1',
            'status' => $status,
            'product_id' => 'prod_plus',
            'customer_id' => 'cus_1',
            'current_period_end' => now()->addMonth()->toIso8601String(),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'customer' => ['id' => 'cus_1', 'external_id' => $this->workspace->public_id],
        ];
    }

    public function test_bad_signature_is_rejected(): void
    {
        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'), key: 'wrong')
            ->assertStatus(401);

        $this->assertSame(Workspace::PLAN_FREE, $this->workspace->fresh()->plan);
    }

    public function test_secret_made_before_the_sept_2026_switch_still_verifies(): void
    {
        // Polar HMAC: the key is the whole `whsec_…` string, not its base64 payload.
        $legacyKey = (string) config('billing.polar.webhook_secret');

        $this->deliver('order.paid', $this->order('ord_legacy', 'prod_pack'), key: $legacyKey)->assertStatus(202);

        $this->assertSame(50, (int) $this->workspace->fresh()->purchased_credits);
    }

    public function test_webhook_route_skips_sessions_and_csrf(): void
    {
        // PHPUnit switches CSRF off, so a request here can't prove the exemption.
        // Check the middleware stack instead: a delivery with neither would also
        // write a sessions row per webhook.
        $router = app('router');
        $middleware = $router->gatherRouteMiddleware($router->getRoutes()->getByName('polar.webhook'));

        $this->assertNotContains(StartSession::class, $middleware);
        $this->assertNotContains(PreventRequestForgery::class, $middleware);
    }

    public function test_stale_timestamp_is_rejected(): void
    {
        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'), timestamp: time() - 3600)
            ->assertStatus(401);
    }

    public function test_plus_order_activates_plus_once(): void
    {
        $this->workspace->forceFill(['credits_balance' => 4])->save();

        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'))->assertStatus(202);

        $fresh = $this->workspace->fresh();
        $this->assertSame(Workspace::PLAN_PRO, $fresh->plan);
        $this->assertSame(100, (int) $fresh->credits_balance);
        $this->assertTrue($fresh->isPlusActive());
        $this->assertSame('cus_1', $fresh->polar_customer_id);
        $this->assertSame('buyer@example.com', $fresh->billing_email);

        // Spend some, then Polar redelivers the same order: no second grant.
        $fresh->forceFill(['credits_balance' => 60])->save();
        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'))->assertStatus(202);

        $this->assertSame(60, (int) $this->workspace->fresh()->credits_balance);
        $this->assertSame(1, BillingOrder::query()->count());
    }

    public function test_renewal_order_refills_plus(): void
    {
        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'));
        $this->workspace->fresh()->forceFill(['credits_balance' => 7, 'purchased_credits' => 10])->save();

        $this->deliver('order.paid', $this->order('ord_2', 'prod_plus', 'subscription_cycle'));

        $this->assertSame(100, (int) $this->workspace->fresh()->credits_balance);
        $this->assertSame(10, (int) $this->workspace->fresh()->purchased_credits);
    }

    public function test_pack_order_adds_purchased_credits_once(): void
    {
        $this->deliver('order.paid', $this->order('ord_p', 'prod_pack'))->assertStatus(202);
        $this->deliver('order.paid', $this->order('ord_p', 'prod_pack'))->assertStatus(202);

        $fresh = $this->workspace->fresh();
        $this->assertSame(50, (int) $fresh->purchased_credits);
        $this->assertSame(20, (int) $fresh->credits_balance);
        $this->assertSame(Workspace::PLAN_FREE, $fresh->plan);
    }

    public function test_refunded_pack_claws_back_credits_clamped_at_zero(): void
    {
        $this->deliver('order.paid', $this->order('ord_p', 'prod_pack'));
        $this->workspace->fresh()->forceFill(['purchased_credits' => 30])->save();

        $this->deliver('order.refunded', $this->order('ord_p', 'prod_pack', extra: ['status' => 'refunded']))
            ->assertStatus(202);

        $this->assertSame(0, (int) $this->workspace->fresh()->purchased_credits);
        $this->assertNotNull(BillingOrder::query()->first()->refunded_at);
    }

    public function test_canceled_keeps_plus_and_revoked_downgrades(): void
    {
        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'));
        $this->deliver('subscription.active', $this->subscription('active'));

        $this->deliver('subscription.canceled', $this->subscription('active', cancelAtPeriodEnd: true));

        $fresh = $this->workspace->fresh();
        $this->assertTrue($fresh->isPlusActive());
        $this->assertTrue($fresh->polar_cancel_at_period_end);
        $this->assertNotNull($fresh->polar_current_period_end);

        $this->deliver('subscription.revoked', $this->subscription('canceled'));

        $fresh = $this->workspace->fresh();
        $this->assertSame(Workspace::PLAN_FREE, $fresh->plan);
        $this->assertFalse($fresh->isPlusActive());
        $this->assertSame(100, (int) $fresh->credits_balance); // leftover kept
    }

    public function test_unknown_workspace_is_acknowledged_and_ignored(): void
    {
        Log::spy();

        $data = $this->order('ord_x', 'prod_pack');
        $data['customer']['external_id'] = 'nope';
        $data['metadata']['workspace_public_id'] = 'nope';

        $this->deliver('order.paid', $data)->assertStatus(202);

        $this->assertSame(0, BillingOrder::query()->count());
        // A paid order nobody can claim is a customer who got nothing: it must be loud.
        Log::shouldHaveReceived('log')->with('warning', 'Polar webhook ignored: no matching workspace', \Mockery::any())->once();
    }

    public function test_cancel_subscription_tool_cancels_at_period_end_in_polar(): void
    {
        Http::fake([
            'sandbox-api.polar.sh/v1/subscriptions/sub_1' => Http::response(['id' => 'sub_1', 'cancel_at_period_end' => true]),
        ]);

        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'));
        $user = $this->workspace->users()->first();

        ReviseMyServer::actingAs($user)->tool(CancelSubscriptionTool::class, ['confirm' => true])
            ->assertHasNoErrors();

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && $request['cancel_at_period_end'] === true);

        $fresh = $this->workspace->fresh();
        $this->assertTrue($fresh->polar_cancel_at_period_end);
        $this->assertSame(Workspace::PLAN_PRO, $fresh->plan);
    }

    public function test_cancel_when_polar_rejects_it_reports_that_and_keeps_the_plan(): void
    {
        Http::fake([
            'sandbox-api.polar.sh/v1/subscriptions/sub_1' => Http::response(['error' => 'ResourceNotFound', 'detail' => 'Subscription not found'], 404),
        ]);

        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'));
        $user = $this->workspace->users()->first();

        ReviseMyServer::actingAs($user)->tool(CancelSubscriptionTool::class, ['confirm' => true])
            ->assertHasErrors(['rejected the request (HTTP 404)', 'do not retry']);

        $fresh = $this->workspace->fresh();
        $this->assertFalse($fresh->polar_cancel_at_period_end);
        $this->assertTrue($fresh->isPlusActive());
    }

    public function test_polar_outage_tells_the_agent_to_retry(): void
    {
        Http::fake([
            'sandbox-api.polar.sh/v1/subscriptions/sub_1' => Http::response('upstream down', 503),
        ]);

        $this->deliver('order.paid', $this->order('ord_1', 'prod_plus', 'subscription_create'));

        ReviseMyServer::actingAs($this->workspace->users()->first())
            ->tool(CancelSubscriptionTool::class, ['confirm' => true])
            ->assertHasErrors(['Polar returned HTTP 503', 'Try again in a moment']);
    }
}
