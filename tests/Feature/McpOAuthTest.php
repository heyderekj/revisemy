<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Connecting an assistant by signing in, end to end.
 *
 * What an MCP client does, in order: find out where to sign in, register
 * itself, send the person to say yes, swap the code for a token, and call the
 * server with it. Here "saying yes" is one Connect button that makes a try
 * workspace — no account — and pasted try tokens go on working beside it.
 * Adapted from Koati's McpOAuthTest.
 */
class McpOAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }
    }

    private function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
    }

    private function register(string $callback = self::CALLBACK): string
    {
        return $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$callback]])
            ->assertSuccessful()
            ->json('client_id');
    }

    /**
     * @return array{0: TestResponse, 1: string}
     */
    private function authorize(string $client, ?string $verifier = null, string $callback = self::CALLBACK): array
    {
        $verifier ??= Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client,
            'redirect_uri' => $callback,
            'state' => 'xyz',
            'scope' => 'mcp:use',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        return [$response, $verifier];
    }

    private function tokenFrom(TestResponse $redirect, string $client, string $verifier): string
    {
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $returned);
        $this->assertSame('xyz', $returned['state'] ?? null);

        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'redirect_uri' => self::CALLBACK,
            'code' => $returned['code'],
            'code_verifier' => $verifier,
        ])->assertOk()->json('access_token');
    }

    public function test_a_client_can_find_out_where_to_sign_in(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/revisemy'));

        $this->getJson('/.well-known/oauth-protected-resource/mcp/revisemy')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/revisemy'));

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_calling_without_signing_in_says_where_to(): void
    {
        $this->postJson('/mcp/revisemy', $this->rpc('tools/list'))
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    /** A missing key pair must not turn the connector probe into a 500. */
    public function test_a_missing_passport_key_still_challenges(): void
    {
        config(['passport.private_key' => null, 'passport.public_key' => null]);

        $hidden = [];

        foreach (['oauth-private.key', 'oauth-public.key'] as $name) {
            $path = storage_path($name);

            if (is_file($path)) {
                rename($path, $path.'.bak');
                $hidden[] = $path;
            }
        }

        try {
            $this->postJson('/mcp/revisemy', $this->rpc('initialize', [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'grok', 'version' => '0'],
            ]))
                ->assertUnauthorized()
                ->assertHeader('WWW-Authenticate');
        } finally {
            foreach ($hidden as $path) {
                rename($path.'.bak', $path);
            }
        }
    }

    public function test_one_click_connect_ends_in_a_working_token(): void
    {
        $client = $this->register();
        [$authorize, $verifier] = $this->authorize($client);

        // Nobody is signed in, so Passport sends the browser to Connect.
        $authorize->assertRedirect('/connect');

        $this->get('/connect')
            ->assertOk()
            ->assertSee('Connect Claude')
            ->assertSee('claude.ai');

        // Connect makes a try workspace and goes straight back to Passport,
        // which skips its own consent page this once.
        $this->post('/connect')->assertRedirectContains('/oauth/authorize');
        $this->assertSame(1, User::count());

        [$approved] = $this->authorize($client, $verifier);
        $approved->assertRedirectContains(self::CALLBACK);
        $token = $this->tokenFrom($approved, $client, $verifier);

        auth()->forgetGuards();
        $this->flushSession();

        $this->withToken($token)
            ->postJson('/mcp/revisemy', $this->rpc('tools/call', ['name' => 'list_reviews', 'arguments' => []]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.count', 0);
    }

    public function test_connecting_with_an_existing_try_token_reaches_that_workspace(): void
    {
        $try = app(TryTokenService::class)->create();
        $client = $this->register();
        $this->authorize($client);

        $this->post('/connect', ['token' => $try['token']])->assertRedirectContains('/oauth/authorize');

        $this->assertSame(1, User::count(), 'No second workspace is made.');
        $this->assertAuthenticatedAs($try['user']);
    }

    public function test_connecting_remembers_the_browser_for_later_visits(): void
    {
        $client = $this->register();
        $this->authorize($client);

        $this->post('/connect')->assertCookie(auth('web')->getRecallerName());
    }

    public function test_a_bad_try_token_says_so_and_makes_nothing(): void
    {
        $client = $this->register();
        $this->authorize($client);

        $this->from('/connect')->post('/connect', ['token' => 'nope'])
            ->assertRedirect('/connect')
            ->assertSessionHasErrors('token');

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_a_second_sign_in_from_a_connected_browser_finishes(): void
    {
        $client = $this->register();
        $this->authorize($client);
        $this->post('/connect');
        $this->authorize($client); // the first one, skipped

        [$again] = $this->authorize($client);
        $again->assertRedirect();
    }

    public function test_connect_without_a_pending_sign_in_explains_itself(): void
    {
        $this->get('/connect')->assertOk()->assertSee('Connect your assistant')->assertSee('Connect Muse');
        $this->post('/connect')->assertRedirect('/connect');
        $this->assertSame(0, User::count());
    }

    /** Try tokens keep working beside OAuth: Cursor and Claude Code still paste one. */
    public function test_a_try_token_still_opens_the_same_door(): void
    {
        $try = app(TryTokenService::class)->create();

        $this->withToken($try['token'])
            ->postJson('/mcp/revisemy', $this->rpc('tools/call', ['name' => 'list_reviews', 'arguments' => []]))
            ->assertOk()
            ->assertJsonPath('result.structuredContent.count', 0);
    }

    /**
     * Registration is open, so a browser that connected before must not hand
     * a stranger's client a code without asking.
     */
    public function test_an_unknown_return_address_always_asks(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $client = $this->register('https://evil.example/cb');
        [$response] = $this->authorize($client, callback: 'https://evil.example/cb');

        $response->assertOk()->assertSee('evil.example');
        $this->assertStringNotContainsString('code=', (string) $response->headers->get('Location'));
    }

    public function test_a_lookalike_assistant_host_is_not_trusted(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $client = $this->register('https://claude.ai.evil.example/cb');
        [$response] = $this->authorize($client, callback: 'https://claude.ai.evil.example/cb');

        $response->assertOk();
        $this->assertNull($response->headers->get('Location'));
    }

    /** Claude Code and Codex listen on a loopback port that changes each time. */
    public function test_a_local_assistant_reconnects_without_asking(): void
    {
        $this->actingAs(User::factory()->create(), 'web');

        $client = $this->register('http://127.0.0.1:43123/callback');
        [$response] = $this->authorize($client, callback: 'http://127.0.0.1:43123/callback');

        $response->assertRedirectContains('http://127.0.0.1:43123/callback?code=');
    }

    /** Clicking Connect is the yes, wherever the client sends the code. */
    public function test_connect_click_is_consent_for_any_return_address(): void
    {
        $client = $this->register('https://muse.example/cb');
        [$first, $verifier] = $this->authorize($client, callback: 'https://muse.example/cb');
        $first->assertRedirect('/connect');

        $this->post('/connect')->assertRedirectContains('/oauth/authorize');

        [$approved] = $this->authorize($client, $verifier, 'https://muse.example/cb');
        $approved->assertRedirectContains('https://muse.example/cb?code=');
    }

    public function test_cursor_and_vscode_clients_can_register(): void
    {
        foreach (['cursor://anysphere.cursor-mcp/oauth/callback', 'vscode://vscode.github-authentication/did-authenticate'] as $callback) {
            $this->postJson('/oauth/register', ['client_name' => 'Desktop', 'redirect_uris' => [$callback]])
                ->assertCreated()
                ->assertJsonPath('redirect_uris.0', $callback);
        }
    }

    /** Grok drops a JSON tool list. It keeps SSE, on its own path. */
    public function test_the_grok_path_streams_the_tool_list(): void
    {
        $try = app(TryTokenService::class)->create();

        $this->withToken($try['token'])
            ->postJson('/mcp/revisemy-grok', $this->rpc('tools/list'), [
                'Accept' => 'application/json, text/event-stream',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'text/event-stream; charset=UTF-8')
            ->assertHeader('X-RateLimit-Limit');
    }

    public function test_grok_on_the_main_path_still_gets_a_stream(): void
    {
        $try = app(TryTokenService::class)->create();

        $this->withToken($try['token'])
            ->postJson('/mcp/revisemy', $this->rpc('tools/list'), [
                'Accept' => 'application/json, text/event-stream',
                'User-Agent' => 'Grok/1.0 (xAI connector)',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'text/event-stream; charset=UTF-8');
    }

    /** Every spec-compliant host asks for both; everyone but Grok gets JSON. */
    public function test_other_hosts_get_json_even_when_they_accept_events(): void
    {
        $try = app(TryTokenService::class)->create();

        $this->withToken($try['token'])
            ->postJson('/mcp/revisemy', $this->rpc('tools/list'), [
                'Accept' => 'application/json, text/event-stream',
            ])
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertHeader('X-RateLimit-Limit')
            ->assertJsonPath('result.tools.0.name', 'create_review');
    }

    public function test_opening_the_mcp_url_is_json_not_an_html_error_page(): void
    {
        foreach (['/mcp/revisemy', '/mcp/revisemy-grok'] as $path) {
            foreach (['get', 'delete'] as $method) {
                $this->{$method}($path)
                    ->assertStatus(405)
                    ->assertHeader('content-type', 'application/json')
                    ->assertHeader('Allow', 'POST')
                    ->assertJsonPath('message', 'Method not allowed. POST JSON-RPC to this URL.');
            }
        }
    }

    public function test_the_grok_path_advertises_itself_as_the_resource(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp/revisemy-grok')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp/revisemy-grok'))
            ->assertJsonPath('scopes_supported.0', 'mcp:use');
    }
}
