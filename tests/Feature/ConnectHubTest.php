<?php

namespace Tests\Feature;

use App\Models\OAuthClient;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Koati's bar for connecting (docs/ui.md § Connecting, after Meta Muse):
 * one list, one screen saying what it will see, proven on the spot,
 * disconnect from the same place.
 */
class ConnectHubTest extends TestCase
{
    use RefreshDatabase;

    private function callTool(string $token): void
    {
        $this->withToken($token)->postJson('/mcp/revisemy', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'list_reviews', 'arguments' => []],
        ])->assertOk();
        $this->app['auth']->forgetGuards();
    }

    public function test_every_host_is_in_the_one_list(): void
    {
        $page = $this->get('/connect')->assertOk();

        foreach (['Claude', 'ChatGPT', 'Cursor', 'VS Code', 'Claude Code', 'Grok', 'Muse', 'Codex'] as $name) {
            $page->assertSee('Connect '.$name);
        }

        $page->assertDontSee('mcp-remote')->assertDontSee('grok.com/connectors');
    }

    public function test_a_first_call_proves_the_connection(): void
    {
        $component = Livewire::test('connect-hub')->call('mintToken');
        $token = (string) $component->get('token');

        $component->assertSee('Waiting for your assistant’s first call');

        $this->travel(2)->seconds();
        $this->callTool($token);

        $component->call('$refresh')->assertSee('is connected')->assertSee('list_reviews');
    }

    public function test_a_call_from_an_app_that_signed_in_names_the_app(): void
    {
        $try = app(TryTokenService::class)->create();
        $client = OAuthClient::query()->forceCreate([
            'id' => (string) Str::uuid(), 'name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb'],
            'grant_types' => ['authorization_code'], 'revoked' => false,
        ]);

        $this->actingAs($try['user'], 'api');
        $try['user']->withAccessToken(new AccessToken(['oauth_client_id' => $client->id]));
        $this->postJson('/mcp/revisemy', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'list_reviews', 'arguments' => []],
        ])->assertOk();

        $this->assertSame('Claude', $try['workspace']->fresh()->assistant_name);
        $this->assertSame('list_reviews', $try['workspace']->fresh()->assistant_last_tool);
    }

    public function test_the_consent_screen_says_what_the_assistant_will_see(): void
    {
        $client = $this->postJson('/oauth/register', ['client_name' => 'ChatGPT', 'redirect_uris' => ['https://chatgpt.com/cb']])->json('client_id');
        $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => $client, 'redirect_uri' => 'https://chatgpt.com/cb',
            'state' => 's', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256',
        ]))->assertRedirect('/connect');

        $this->get('/connect')
            ->assertSee('ChatGPT can')
            ->assertSee('Read your marks and what you decided')
            ->assertSee('Stays with you')
            ->assertSee('Approving, asking for changes, verifying a fix');
    }

    public function test_disconnecting_an_app_revokes_what_it_holds(): void
    {
        $try = app(TryTokenService::class)->create();
        $client = OAuthClient::query()->forceCreate([
            'id' => (string) Str::uuid(), 'name' => 'Claude', 'redirect_uris' => ['https://claude.ai/cb'],
            'grant_types' => ['authorization_code'], 'revoked' => false,
        ]);
        Token::query()->forceCreate([
            'id' => Str::random(80), 'user_id' => $try['user']->id, 'client_id' => $client->id,
            'name' => null, 'scopes' => ['mcp:use'], 'revoked' => false, 'expires_at' => now()->addHour(),
        ]);

        Livewire::test('connected-assistants', ['workspaceId' => $try['workspace']->id])
            ->assertSee('Claude')
            ->call('disconnect', 'app', (string) $client->id)
            ->assertDontSee('Signed in');

        $this->assertSame(0, Token::query()->where('client_id', $client->id)->where('revoked', false)->count());
    }

    public function test_revoking_a_try_token_closes_the_door(): void
    {
        $try = app(TryTokenService::class)->create();
        $id = (string) explode('|', $try['token'], 2)[0];

        Livewire::test('connected-assistants', ['workspaceId' => $try['workspace']->id])
            ->call('disconnect', 'token', $id);

        $this->withToken($try['token'])->postJson('/mcp/revisemy', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();
    }
}
