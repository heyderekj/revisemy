<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * What an MCP client does to connect, step by step: register, send the
 * person to sign in, swap the code for a token, call the server with it.
 */
trait SignsInAssistants
{
    protected const CALLBACK = 'https://claude.ai/api/mcp/auth_callback';

    /**
     * @return array<string, mixed>
     */
    protected function rpc(string $method, array $params = []): array
    {
        return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params];
    }

    protected function register(string $callback = self::CALLBACK): string
    {
        return $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => [$callback]])
            ->assertSuccessful()
            ->json('client_id');
    }

    /**
     * @param  array<string, string>  $extra
     * @return array{0: TestResponse, 1: string}
     */
    protected function authorize(string $client, ?string $verifier = null, string $callback = self::CALLBACK, array $extra = []): array
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
            ...$extra,
        ]));

        return [$response, $verifier];
    }

    protected function codeFrom(TestResponse $redirect): string
    {
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $returned);
        $this->assertSame('xyz', $returned['state'] ?? null);

        return (string) $returned['code'];
    }

    protected function tokenFrom(TestResponse $redirect, string $client, string $verifier): string
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'redirect_uri' => self::CALLBACK,
            'code' => $this->codeFrom($redirect),
            'code_verifier' => $verifier,
        ])->assertOk()->json('access_token');
    }

    /**
     * Connect a fresh browser through /connect and swap the code the way
     * Claude does: form-encoded, with the RFC 8707 resource.
     *
     * @return array<string, mixed>
     */
    protected function connectLikeClaude(string $callback = self::CALLBACK): array
    {
        $client = $this->register($callback);
        [$first, $verifier] = $this->authorize($client, callback: $callback);
        $first->assertRedirect('/connect');
        $this->post('/connect')->assertRedirectContains('/oauth/authorize');
        [$approved] = $this->authorize($client, $verifier, $callback, ['resource' => url('/mcp/revisemy')]);

        $tokens = $this->post('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client,
            'redirect_uri' => $callback,
            'code' => $this->codeFrom($approved),
            'code_verifier' => $verifier,
            'resource' => url('/mcp/revisemy'),
        ])->assertOk()->json();

        // What follows comes from Claude's servers, not this browser.
        auth()->forgetGuards();
        $this->flushSession();

        return [...$tokens, 'client_id' => $client];
    }
}
