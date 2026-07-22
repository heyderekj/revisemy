<?php

namespace Tests\Feature;

use App\Services\TryTokenGate;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class TryTokenRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.try_token.per_hour' => 3,
            'billing.try_token.per_day' => 3,
        ]);

        RateLimiter::clear((new TryTokenGate)->hourKey('127.0.0.1'));
        RateLimiter::clear((new TryTokenGate)->dayKey('127.0.0.1'));
    }

    public function test_fourth_api_mint_in_hour_returns_429(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/try-token')->assertCreated();
        }

        $this->postJson('/api/try-token')
            ->assertStatus(429)
            ->assertJsonPath('message', TryTokenGate::MESSAGE);
    }

    public function test_daily_cap_blocks_after_three_mints(): void
    {
        config([
            'billing.try_token.per_hour' => 100,
            'billing.try_token.per_day' => 3,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/try-token')->assertCreated();
        }

        $this->postJson('/api/try-token')->assertStatus(429);
    }

    public function test_api_and_livewire_share_the_same_mint_budget(): void
    {
        $this->postJson('/api/try-token')->assertCreated();
        $this->postJson('/api/try-token')->assertCreated();

        Livewire::test('home')
            ->call('getTryToken')
            ->assertSet('error', null)
            ->assertNotSet('token', null);

        Livewire::test('home')
            ->call('getTryToken')
            ->assertSet('error', TryTokenGate::MESSAGE);
    }

    public function test_expired_try_token_is_rejected_on_api_billing(): void
    {
        config([
            'billing.try_token.per_hour' => 1000,
            'billing.try_token.per_day' => 1000,
        ]);

        $result = app(TryTokenService::class)->create();
        $plain = $result['token'];
        $id = explode('|', $plain, 2)[0];

        PersonalAccessToken::query()->whereKey($id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($plain)
            ->getJson('/api/billing')
            ->assertUnauthorized();
    }
}
