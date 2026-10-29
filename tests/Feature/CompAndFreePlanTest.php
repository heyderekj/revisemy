<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\CreditsService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompAndFreePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
    }

    public function test_comp_gives_plus_and_refills_it_each_month(): void
    {
        $workspace = app(TryTokenService::class)->create()['user']->workspace;

        $this->artisan('revisemy:comp', ['workspace' => $workspace->public_id, '--note' => 'Derek'])->assertSuccessful();

        $workspace->refresh();
        $this->assertTrue($workspace->isComped());
        $this->assertSame(Workspace::PLAN_PRO, $workspace->plan);
        $this->assertSame(100, (int) $workspace->credits_balance);
        $this->assertSame('Derek', $workspace->comp_note);
        $this->assertSame(90, $workspace->reviewRetentionDays());

        $workspace->forceFill(['credits_balance' => 3, 'credits_period_start' => now()->subMonth()->subDay()])->save();
        $workspace = app(CreditsService::class)->ensurePeriod($workspace);

        $this->assertSame(100, (int) $workspace->credits_balance);
    }

    public function test_comp_takes_an_owner_review_link(): void
    {
        $workspace = app(TryTokenService::class)->create()['user']->workspace;
        $review = Review::query()->create(['workspace_id' => $workspace->id, 'title' => 'Hero']);

        $this->artisan('revisemy:comp', ['workspace' => 'https://revisemy.com/r/'.$review->token])->assertSuccessful();
        $this->assertTrue($workspace->fresh()->isComped());

        $this->artisan('revisemy:comp', ['workspace' => $review->share_token])->assertFailed();
    }

    public function test_comp_off_drops_to_try_and_billing_offers_no_checkout_while_comped(): void
    {
        $workspace = app(TryTokenService::class)->create()['user']->workspace;
        config(['billing.pricing_enabled' => true, 'services.polar.access_token' => 'x', 'services.polar.webhook_secret' => 'y']);

        $this->artisan('revisemy:comp', ['workspace' => $workspace->public_id]);
        $status = app(BillingService::class)->status($workspace->fresh());
        $this->assertTrue($status['comped']);
        $this->assertFalse($status['checkout_available']);

        $this->artisan('revisemy:comp', ['workspace' => $workspace->public_id, '--off' => true]);
        $this->assertSame(Workspace::PLAN_FREE, $workspace->fresh()->plan);
        $this->assertFalse($workspace->fresh()->isComped());
    }

    public function test_one_address_gets_a_shared_daily_allowance_of_new_workspaces(): void
    {
        config([
            'billing.try_token.workspaces_per_day' => 2,
            'billing.try_token.per_day' => 10,
            'billing.try_token.per_hour' => 10,
        ]);

        $this->postJson('/api/try-token')->assertSuccessful();
        $this->postJson('/api/try-token')->assertSuccessful();
        $this->postJson('/api/try-token')->assertStatus(429);

        // The same allowance covers a review started on the site.
        $this->post('/new', ['kind' => 'images', 'images' => [UploadedFile::fake()->image('a.png')]])
            ->assertSessionHasErrors('images');
        $this->assertSame(0, Review::query()->count());
    }

    public function test_turnstile_guards_a_new_workspace_from_the_site_when_configured(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])
            ->push(['success' => true])]);

        $this->get('/new')->assertSee('cf-turnstile', false);

        $this->post('/new', ['kind' => 'images', 'images' => [UploadedFile::fake()->image('a.png')], 'cf-turnstile-response' => 'bad'])
            ->assertSessionHasErrors('images');
        $this->assertSame(0, Review::query()->count());

        $this->post('/new', ['kind' => 'images', 'images' => [UploadedFile::fake()->image('a.png')], 'cf-turnstile-response' => 'good'])
            ->assertRedirect();
        $this->assertSame(1, Review::query()->count());

        // A browser that already has a workspace isn't asked again.
        $this->get('/new')->assertDontSee('cf-turnstile', false);
    }
}
