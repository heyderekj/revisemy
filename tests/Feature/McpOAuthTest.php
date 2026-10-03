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

    private function register(): string
    {
        return $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [self::CALLBACK]])
            ->assertSuccessful()
            ->json('client_id');
    }

    /**
     * @return array{0: TestResponse, 1: string}
     */
    private function authorize(string $client, ?string $verifier = null): array
    {
        $verifier ??= Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $response = $this->get('/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client,
            'redirect_uri' => self::CALLBACK,
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

    public function test_a_second_sign_in_from_a_connected_browser_asks_first(): void
    {
        $client = $this->register();
        $this->authorize($client);
        $this->post('/connect');
        $this->authorize($client); // the first one, skipped

        [$again] = $this->authorize($client);
        $again->assertOk()->assertSee('Connect Claude')->assertSee('Not now');
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
}
