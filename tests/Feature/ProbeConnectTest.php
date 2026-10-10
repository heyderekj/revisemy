<?php

namespace Tests\Feature;

use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * revisemy:probe-connect runs the whole Claude sign-in against a live
 * address. Here its requests are answered by this app instead, so the probe
 * and the flow it checks are tested together.
 */
class ProbeConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }

        Http::fake(fn (Request $request) => $this->answer($request));
    }

    public function test_the_probe_connects_like_claude_and_says_so(): void
    {
        $token = app(TryTokenService::class)->create()['token'];

        $this->artisan('revisemy:probe-connect', ['base' => url('/'), '--token' => $token])
            ->expectsOutputToContain('Connect works at')
            ->assertSuccessful();
    }

    public function test_the_probe_covers_claude_codes_localhost_callback(): void
    {
        $token = app(TryTokenService::class)->create()['token'];

        $this->artisan('revisemy:probe-connect', ['base' => url('/'), '--token' => $token, '--callback' => 'http://localhost:3118/callback'])
            ->assertSuccessful();
    }

    public function test_the_probe_names_the_step_that_broke(): void
    {
        $this->artisan('revisemy:probe-connect', ['base' => url('/'), '--token' => 'not-a-try-token'])
            ->expectsOutputToContain('Connect sent the browser back to itself')
            ->assertFailed();
    }

    /** One request from the probe, answered by the app as a fresh request would be. */
    private function answer(Request $request): mixed
    {
        $uri = $request->toPsrRequest()->getUri();
        $path = $uri->getPath().($uri->getQuery() !== '' ? '?'.$uri->getQuery() : '');

        $server = [];
        foreach ($request->headers() as $name => $values) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_'.$key] = implode(', ', $values);
        }

        $cookies = [];
        foreach (array_filter(explode(';', $request->header('Cookie')[0] ?? '')) as $pair) {
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $cookies[$name] = urldecode($value);
        }

        $parameters = [];
        $content = $request->body();
        if ($request->isForm()) {
            parse_str($content, $parameters);
            $content = null;
        }

        // Each request starts clean, as it would on a server.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        $response = $this->call($request->method(), $path, $parameters, $cookies, [], $server, $content);

        $headers = $response->baseResponse->headers->allPreserveCaseWithoutCookies();
        $headers['Set-Cookie'] = array_map('strval', $response->baseResponse->headers->getCookies());

        return Http::response($response->getContent(), $response->getStatusCode(), $headers);
    }
}
