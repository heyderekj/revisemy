<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateMcp;
use App\Models\OAuthClient;
use App\Models\User;
use App\Services\TryTokenGate;
use App\Support\PassportKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Passport\Token;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\Concerns\SignsInAssistants;
use Tests\TestCase;

/**
 * Connect has to survive what production throws at it, not just what a fresh
 * test install has.
 *
 * On 2026-10-10 Claude registered, signed in and got a token, then every MCP
 * call came back 401: the public key on the server didn't match the private
 * one. Tests used a fresh key pair from files, so they passed. These cover
 * keys pasted the way Laravel Cloud holds them, a token turned down saying
 * why, refreshes that lose their answer, Claude Code's changing port, and a
 * sign-in that loses its way.
 */
class ConnectReliabilityTest extends TestCase
{
    use RefreshDatabase, SignsInAssistants;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }

        PassportKeys::flush();
        (new ReflectionProperty(AuthenticateMcp::class, 'reportedKeys'))->setValue(null, false);
    }

    protected function tearDown(): void
    {
        PassportKeys::flush();

        parent::tearDown();
    }

    public function test_claude_connects_end_to_end_the_way_claude_sends_it(): void
    {
        $tokens = $this->connectLikeClaude();

        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertNotEmpty($tokens['refresh_token']);

        $this->withToken($tokens['access_token'])
            ->postJson('/mcp/revisemy', $this->rpc('initialize', [
                'protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'claude-ai', 'version' => '0.1'],
            ]))
            ->assertOk()
            ->assertJsonPath('result.serverInfo.name', fn ($name) => filled($name));

        $this->withToken($tokens['access_token'])
            ->postJson('/mcp/revisemy', $this->rpc('tools/list'))
            ->assertOk()
            ->assertJsonPath('result.tools.0.name', 'create_review');
    }

    /** The production bug: keys as one line with \n, and a public key from another pair. */
    public function test_a_public_key_that_does_not_match_no_longer_turns_tokens_down(): void
    {
        [$private] = $this->keyPair();
        [, $otherPublic] = $this->keyPair();
        $this->useKeys($private, $otherPublic);

        $tokens = $this->connectLikeClaude();

        $this->withToken($tokens['access_token'])
            ->postJson('/mcp/revisemy', $this->rpc('tools/list'))
            ->assertOk();

        $this->assertTrue(PassportKeys::configuredPublicMismatch());
        $this->assertTrue(PassportKeys::roundTrip());
    }

    public function test_no_public_key_at_all_still_checks_tokens(): void
    {
        [$private] = $this->keyPair();
        $this->useKeys($private, null);
        rename(storage_path('oauth-public.key'), storage_path('oauth-public.key.bak'));

        try {
            $tokens = $this->connectLikeClaude();

            $this->withToken($tokens['access_token'])
                ->postJson('/mcp/revisemy', $this->rpc('tools/list'))
                ->assertOk();
        } finally {
            rename(storage_path('oauth-public.key.bak'), storage_path('oauth-public.key'));
        }
    }

    public function test_a_token_signed_by_another_key_is_logged_and_reported(): void
    {
        $tokens = $this->connectLikeClaude();

        // The server's keys change under a token it already handed out.
        [$private] = $this->keyPair();
        $this->useKeys($private, null);
        $logs = $this->recordLogs();

        $response = $this->withToken($tokens['access_token'])->postJson('/mcp/revisemy', $this->rpc('tools/list'));

        $response->assertUnauthorized();
        $this->assertStringContainsString('error="invalid_token"', (string) $response->headers->get('WWW-Authenticate'));

        $rejected = $logs->find('connect.mcp.rejected');
        $this->assertSame('Token signature mismatch', $rejected['context']['reason'] ?? null);
        $this->assertNotNull($logs->find('Connector tokens cannot be checked', partial: true), 'A key problem is reported, not just logged.');
    }

    public function test_a_token_turned_down_asks_for_a_refresh_and_says_why(): void
    {
        $logs = $this->recordLogs();

        $response = $this->withToken('aaa.bbb.ccc')->postJson('/mcp/revisemy', $this->rpc('tools/list'));

        $response->assertUnauthorized();
        $header = (string) $response->headers->get('WWW-Authenticate');
        $this->assertStringContainsString('error="invalid_token"', $header);
        $this->assertStringContainsString('scope="mcp:use"', $header);
        $this->assertStringContainsString('resource_metadata="'.url('/.well-known/oauth-protected-resource/mcp/revisemy').'"', $header);

        $rejected = $logs->find('connect.mcp.rejected');
        $this->assertSame('oauth', $rejected['context']['kind'] ?? null);
        $this->assertNotEmpty($rejected['context']['reason'] ?? null);
        $this->assertStringNotContainsString('aaa.bbb.ccc', json_encode($logs->lines));
    }

    public function test_a_first_probe_gets_a_plain_challenge(): void
    {
        $logs = $this->recordLogs();

        $response = $this->postJson('/mcp/revisemy', $this->rpc('initialize'));

        $response->assertUnauthorized();
        $header = (string) $response->headers->get('WWW-Authenticate');
        $this->assertStringContainsString('scope="mcp:use"', $header);
        $this->assertStringNotContainsString('error=', $header);
        $this->assertNull($logs->find('connect.mcp.rejected'));
    }

    public function test_a_refresh_that_lost_its_answer_can_be_asked_again_for_a_minute(): void
    {
        $tokens = $this->connectLikeClaude();
        $refresh = fn (string $token) => $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token,
            'client_id' => $tokens['client_id'],
            'resource' => url('/mcp/revisemy'),
        ]);

        $fresh = $refresh($tokens['refresh_token'])->assertOk()->json();
        $this->assertNotSame($tokens['refresh_token'], $fresh['refresh_token']);

        // The answer was lost: the old token still works, briefly.
        $refresh($tokens['refresh_token'])->assertOk();

        $this->travel(61)->seconds();

        $refresh($tokens['refresh_token'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_grant');
        $refresh($fresh['refresh_token'])->assertOk();
    }

    public function test_disconnect_leaves_no_refresh_grace(): void
    {
        $tokens = $this->connectLikeClaude();
        $refresh = fn (string $token) => $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token,
            'client_id' => $tokens['client_id'],
        ]);
        $fresh = $refresh($tokens['refresh_token'])->assertOk()->json();
        $workspace = User::query()->firstOrFail()->workspace;

        Livewire::test('connected-assistants', ['workspaceId' => $workspace->id])
            ->call('disconnect', 'app', $tokens['client_id']);

        $refresh($tokens['refresh_token'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
        $refresh($fresh['refresh_token'])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    /** Claude Code listens on a new localhost port each time it signs in. */
    public function test_claude_code_can_come_back_on_another_localhost_port(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $client = $this->register('http://localhost:3118/callback');

        [$response, $verifier] = $this->authorize($client, callback: 'http://localhost:40123/callback');
        $response->assertRedirectContains('http://localhost:40123/callback?code=');

        $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'redirect_uri' => 'http://localhost:40123/callback',
            'code' => $this->codeFrom($response),
            'code_verifier' => $verifier,
        ])->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_only_the_port_of_a_localhost_callback_may_change(): void
    {
        $this->actingAs(User::factory()->create(), 'web');
        $client = $this->register('http://localhost:3118/callback');

        foreach (['http://localhost:40123/elsewhere', 'http://evil.test:3118/callback', 'https://localhost:3118/callback'] as $callback) {
            [$response] = $this->authorize($client, callback: $callback);

            $response->assertStatus(401);
            $this->assertNull($response->headers->get('Location'), $callback);
        }
    }

    public function test_discovery_says_how_a_public_client_connects(): void
    {
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('token_endpoint', url('/oauth/token'))
            ->assertJsonPath('token_endpoint_auth_methods_supported', ['none'])
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonPath('grant_types_supported', ['authorization_code', 'refresh_token']);

        $this->getJson('/.well-known/oauth-authorization-server/mcp/revisemy')
            ->assertOk()
            ->assertJsonPath('issuer', url('/'));

        foreach (['' => 'mcp/revisemy', '/mcp/revisemy' => 'mcp/revisemy', '/mcp/revisemy-grok' => 'mcp/revisemy-grok'] as $suffix => $resource) {
            $this->getJson('/.well-known/oauth-protected-resource'.$suffix)
                ->assertOk()
                ->assertJsonPath('resource', url('/'.$resource))
                ->assertJsonPath('authorization_servers', [url('/')])
                ->assertJsonPath('bearer_methods_supported', ['header'])
                ->assertJsonPath('scopes_supported', ['mcp:use']);
        }
    }

    public function test_browser_clients_can_reach_discovery_and_the_token_swap(): void
    {
        $this->call('OPTIONS', '/oauth/token', server: [
            'HTTP_ORIGIN' => 'https://inspector.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', '*');

        $this->postJson('/mcp/revisemy', $this->rpc('initialize'), ['Origin' => 'https://inspector.example'])
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Expose-Headers');
    }

    public function test_token_swaps_are_limited_per_assistant_not_per_address(): void
    {
        $route = Route::getRoutes()->getByName('passport.token');

        $this->assertContains('throttle:oauth-token', $route->gatherMiddleware());
        $this->assertNotContains('throttle', $route->gatherMiddleware());
    }

    public function test_a_lost_sign_in_says_to_start_again(): void
    {
        $this->followingRedirects()
            ->post('/connect')
            ->assertOk()
            ->assertSee('That connect attempt expired. Start it again from your assistant.');
    }

    public function test_connect_has_its_own_allowance_and_says_which_one_ran_out(): void
    {
        config(['billing.try_token.connect_per_hour' => 1]);

        [$first] = $this->authorize($this->register());
        $first->assertRedirect('/connect');
        $this->post('/connect')->assertRedirectContains('/oauth/authorize');

        // Another browser on the same network.
        auth('web')->logout();
        $this->flushSession();
        $this->authorize($this->register());
        $this->from('/connect')->post('/connect')
            ->assertRedirect('/connect')
            ->assertSessionHasErrors(['token' => TryTokenGate::HOUR_MESSAGE]);

        // Get a try token's allowance is untouched.
        $this->assertSame(0, RateLimiter::attempts(app(TryTokenGate::class)->hourKey('127.0.0.1')));
    }

    public function test_registrations_that_never_connected_are_cleared_after_a_week(): void
    {
        $old = $this->client(now()->subDays(8));
        $oldButConnected = $this->client(now()->subDays(8));
        $new = $this->client(now()->subDay());

        Token::query()->forceCreate([
            'id' => Str::random(80), 'user_id' => User::factory()->create()->id, 'client_id' => $oldButConnected->id,
            'scopes' => [], 'revoked' => false, 'expires_at' => now()->addHour(),
        ]);

        $this->artisan('revisemy:prune-oauth-clients')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($oldButConnected);
        $this->assertModelExists($new);
    }

    public function test_the_connect_check_fails_a_deploy_when_tokens_cannot_be_checked(): void
    {
        $this->artisan('revisemy:check', ['--connect' => true])->assertSuccessful();

        config(['passport.private_key' => 'not a key']);
        PassportKeys::flush();

        $this->artisan('revisemy:check', ['--connect' => true])
            ->expectsOutputToContain('can’t be read as a private key')
            ->assertFailed();
    }

    public function test_a_bad_sign_in_link_gets_a_page_not_json(): void
    {
        $client = $this->register();

        [$response] = $this->authorize($client, callback: 'https://claude.ai/somewhere-else');

        $response->assertStatus(401)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->assertSee('This sign-in link doesn’t work')
            ->assertSee('In Claude, remove ReviseMy from your connectors.')
            ->assertSee(url('/mcp/revisemy'))
            ->assertSee('invalid_client');

        $this->getJson('/oauth/authorize?client_id='.$client)
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_connect_names_the_assistant_by_where_it_returns_not_what_it_calls_itself(): void
    {
        $client = $this->postJson('/oauth/register', ['client_name' => 'claude-ai-connector', 'redirect_uris' => [self::CALLBACK]])->json('client_id');
        $this->authorize($client);

        $this->get('/connect')
            ->assertSee('Connect Claude')
            ->assertDontSee('claude-ai-connector')
            ->assertDontSee('calls itself');
    }

    public function test_a_lookalike_is_named_and_warned_about(): void
    {
        $client = $this->register('https://evil.example/cb');
        $this->authorize($client, callback: 'https://evil.example/cb');

        $this->get('/connect')
            ->assertSee('Connect evil.example')
            ->assertDontSee('>Connect Claude<', false)
            ->assertSee('This app calls itself Claude, but it sends you back to', false)
            ->assertSee('https://evil.example');
    }

    public function test_claude_code_on_localhost_is_not_called_a_lookalike(): void
    {
        $client = $this->postJson('/oauth/register', ['client_name' => 'Claude Code (revisemy)', 'redirect_uris' => ['http://localhost:3118/callback']])->json('client_id');
        $this->authorize($client, callback: 'http://localhost:3118/callback');

        $this->get('/connect')
            ->assertSee('Connect Claude Code (revisemy)')
            ->assertDontSee('calls itself');
    }

    public function test_one_assistant_connected_twice_is_one_row_and_one_disconnect(): void
    {
        $first = $this->connectLikeClaude();
        $user = User::query()->firstOrFail();

        // Claude registers again on a reconnect, from a browser that's still signed in.
        $this->actingAs($user, 'web');
        $second = $this->register();
        [$approved, $verifier] = $this->authorize($second);
        $this->tokenFrom($approved, $second, $verifier);

        $component = Livewire::test('connected-assistants', ['workspaceId' => $user->workspace_id]);
        $apps = collect($component->instance()->connections)->where('kind', 'app');

        $this->assertCount(1, $apps);
        $this->assertSame('Claude', $apps->first()['name']);
        $this->assertSame('claude', $apps->first()['icon']);

        $component->call('disconnect', 'app', $apps->first()['id']);

        $this->assertSame(0, Token::query()->where('revoked', false)->count());
        $this->assertNotEmpty($first['access_token']);
    }

    /**
     * @return array{0: string, 1: string} PEM private and public key, each as one line with \n
     */
    private function keyPair(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $public = openssl_pkey_get_details($key)['key'];

        // How Laravel Cloud holds a multi-line value: one line, \n for breaks.
        return [str_replace("\n", '\\n', $private), str_replace("\n", '\\n', $public)];
    }

    private function useKeys(?string $private, ?string $public): void
    {
        config(['passport.private_key' => $private, 'passport.public_key' => $public]);
        PassportKeys::flush();
        $this->app->forgetInstance(AuthorizationServer::class);
        $this->app->forgetInstance(ResourceServer::class);
    }

    private function client(\DateTimeInterface $createdAt): OAuthClient
    {
        $client = OAuthClient::query()->forceCreate([
            'id' => (string) Str::uuid(), 'name' => 'Claude', 'redirect_uris' => [self::CALLBACK],
            'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false,
        ]);
        $client->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $client;
    }

    /** Swap the logger for one that keeps every line, to check what Connect wrote down. */
    private function recordLogs(): object
    {
        $recorder = new class
        {
            /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
            public array $lines = [];

            public function channel(?string $channel = null): static
            {
                return $this;
            }

            public function log(mixed $level, string|\Stringable $message, array $context = []): void
            {
                $this->lines[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
            }

            /**
             * @return array{level: string, message: string, context: array<string, mixed>}|null
             */
            public function find(string $message, bool $partial = false): ?array
            {
                foreach ($this->lines as $line) {
                    if ($partial ? str_contains($line['message'], $message) : $line['message'] === $message) {
                        return $line;
                    }
                }

                return null;
            }

            public function __call(string $method, array $arguments): mixed
            {
                if (in_array($method, ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], true)) {
                    $this->log($method, $arguments[0] ?? '', $arguments[1] ?? []);
                }

                return $this;
            }
        };

        Log::swap($recorder);

        return $recorder;
    }
}
