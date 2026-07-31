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

    public function test_shipped_defaults_keep_both_windows_live(): void
    {
        // Read the env() fallbacks out of the config source rather than calling
        // config(): phpunit.xml pins both windows to 1000 so the rest of the
        // suite is not rate-limited, and setUp() overrides them again per test,
        // so neither can show what actually ships.
        $source = (string) file_get_contents(config_path('billing.php'));

        $default = function (string $key) use ($source): int {
            $this->assertSame(
                1,
                preg_match('/'.preg_quote($key, '/').'\'\s*,\s*(\d+)\)/', $source, $m),
                "Could not read the {$key} default out of config/billing.php."
            );

            return (int) $m[1];
        };

        // Equal values make the hourly gate unreachable: the daily counter caps
        // out first, so the hourly one never fires.
        $this->assertGreaterThan(
            $default('REVISEMY_TRY_TOKEN_PER_HOUR'),
            $default('REVISEMY_TRY_TOKEN_PER_DAY'),
            'per_day must exceed per_hour or the hourly mint gate is dead code.'
        );
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
