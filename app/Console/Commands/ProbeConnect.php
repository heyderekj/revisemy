<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Connect an assistant the way Claude does, end to end, and say which step
 * broke.
 *
 * Find the sign-in documents from a 401, register, send a browser through
 * /connect, swap the code for a token (form-encoded, with `resource`), call
 * the MCP URL with it, then refresh. Each step is timed against Claude's
 * limits: 10 seconds for discovery, registration and the token swap, 30 for
 * a refresh.
 *
 * On 2026-10-10 every step up to the token worked and every MCP call after it
 * was turned down. Tests passed and nothing was logged, so only a real
 * round trip like this one shows that.
 *
 * Pass --token with a try token so no new workspace is made. Each run
 * registers one client; revisemy:prune-oauth-clients clears old ones.
 */
class ProbeConnect extends Command
{
    protected $signature = 'revisemy:probe-connect
        {base? : The ReviseMy address to probe (defaults to APP_URL)}
        {--token= : A try token to connect with, so no workspace is made (defaults to REVISEMY_PROBE_TOKEN)}
        {--callback=https://claude.ai/api/mcp/auth_callback : The callback to register (http://localhost:3118/callback for Claude Code)}
        {--report : Report a failure as an error, for the schedule}';

    protected $description = 'Connect like Claude does and say which step fails';

    private const BUDGET = 10_000;

    private const REFRESH_BUDGET = 30_000;

    /** @var array<string, string> */
    private array $cookies = [];

    private string $host = '';

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $base = rtrim((string) ($this->argument('base') ?: config('app.url')), '/');
        $this->host = (string) parse_url($base, PHP_URL_HOST);
        $failure = null;

        try {
            $this->probe($base, (string) ($this->option('token') ?: config('revisemy.oauth.probe_token')), (string) $this->option('callback'));
        } catch (Throwable $e) {
            $failure = $e->getMessage();
        }

        $this->table(['Step', 'Status', 'Time', 'Note'], $this->rows);

        if ($failure === null) {
            $this->info("Connect works at {$base}.");

            return self::SUCCESS;
        }

        $this->error("Connect is broken at {$base}: {$failure}");

        if ($this->option('report')) {
            report(new RuntimeException("Connect probe failed at {$base}: {$failure}"));
        }

        return self::FAILURE;
    }

    private function probe(string $base, string $tryToken, string $callback): void
    {
        $mcpUrl = $base.'/mcp/revisemy';

        // 1. Discovery: a 401 that names the protected-resource document.
        $probe = $this->step('MCP URL without a token', 401, fn () => $this->http()->asJson()
            ->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->post($mcpUrl, $this->rpc('initialize', $this->initializeParams())));
        preg_match('/resource_metadata="([^"]+)"/', (string) $probe->header('WWW-Authenticate'), $match);
        $metadataUrl = $match[1] ?? $this->broken('the 401 has no resource_metadata in WWW-Authenticate');

        $resource = $this->step('Protected-resource document', 200, fn () => $this->http()->get($metadataUrl))->json();
        if (($resource['resource'] ?? null) !== $mcpUrl) {
            $this->broken('resource is '.json_encode($resource['resource'] ?? null).", not {$mcpUrl}");
        }
        $issuer = (string) ($resource['authorization_servers'][0] ?? $this->broken('no authorization_servers'));

        $server = $this->step('Authorization server document', 200, fn () => $this->http()->get(rtrim($issuer, '/').'/.well-known/oauth-authorization-server'))->json();
        if (! in_array('S256', (array) ($server['code_challenge_methods_supported'] ?? []), true)) {
            $this->broken('S256 is not in code_challenge_methods_supported, so Claude refuses to start');
        }

        // 2. Registration, the way Claude introduces itself.
        $clientId = (string) ($this->step('Register', 201, fn () => $this->http()->asJson()->post((string) $server['registration_endpoint'], [
            'client_name' => 'ReviseMy connect probe',
            'redirect_uris' => [$callback],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'mcp:use',
        ]))->json('client_id') ?? $this->broken('registration returned no client_id'));

        // 3. The browser: authorize → /connect → authorize → callback with a code.
        $verifier = Str::random(64);
        $state = Str::random(32);
        $authorizeUrl = $server['authorization_endpoint'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $callback,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'state' => $state,
            'scope' => 'mcp:use',
            'resource' => $mcpUrl,
        ]);

        $location = (string) $this->step('Authorize', 302, fn () => $this->http()->get($authorizeUrl))->header('Location');

        if (! str_starts_with($location, $callback)) {
            $page = $this->step('Connect page', 200, fn () => $this->http()->get($location));
            preg_match('/name="_token"\s+value="([^"]+)"/', $page->body(), $csrf);

            $location = (string) $this->step('Click Connect', 302, fn () => $this->http()->asForm()->post($base.'/connect', array_filter([
                '_token' => $csrf[1] ?? $this->broken('the Connect page has no CSRF field'),
                'token' => $tryToken,
            ])))->header('Location');

            if (str_contains($location, '/connect')) {
                $this->broken('Connect sent the browser back to itself (a try limit, a bad --token, or a lost session)');
            }

            $location = (string) $this->step('Authorize again', 302, fn () => $this->http()->get($location))->header('Location');
        }

        parse_str((string) parse_url($location, PHP_URL_QUERY), $returned);
        if (! str_starts_with($location, $callback) || isset($returned['error'])) {
            $this->broken('the browser went to '.Str::before($location, '?').(isset($returned['error']) ? " with error={$returned['error']}" : '').' instead of back to the assistant');
        }
        if (($returned['state'] ?? null) !== $state) {
            $this->broken('the state that came back is not the one sent');
        }

        // 4. The token swap, form-encoded like Claude sends it.
        $tokens = $this->step('Swap the code for a token', 200, fn () => $this->http()->asForm()->post((string) $server['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => (string) ($returned['code'] ?? ''),
            'redirect_uri' => $callback,
            'client_id' => $clientId,
            'code_verifier' => $verifier,
            'resource' => $mcpUrl,
        ]))->json();

        // 5. The calls that failed on 2026-10-10.
        $this->callMcp('initialize', $mcpUrl, (string) ($tokens['access_token'] ?? ''), $this->initializeParams(), 'serverInfo');
        $this->callMcp('tools/list', $mcpUrl, (string) ($tokens['access_token'] ?? ''), [], 'tools', 'create_review');

        // 6. An hour later, the refresh.
        $refreshed = $this->step('Refresh the token', 200, fn () => $this->http()->asForm()->post((string) $server['token_endpoint'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) ($tokens['refresh_token'] ?? ''),
            'client_id' => $clientId,
            'resource' => $mcpUrl,
        ]), self::REFRESH_BUDGET)->json();

        $this->callMcp('tools/list', $mcpUrl, (string) ($refreshed['access_token'] ?? ''), [], 'tools', 'create_review', 'tools/list (refreshed token)');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function callMcp(string $method, string $url, string $token, array $params, string $expect, ?string $contains = null, ?string $label = null): void
    {
        $response = $this->step($label ?? $method, 200, fn () => $this->http()->asJson()->withToken($token)
            ->withHeaders(['Accept' => 'application/json, text/event-stream'])
            ->post($url, $this->rpc($method, $params)));

        $body = $response->body();
        if (str_contains((string) $response->header('Content-Type'), 'text/event-stream') && preg_match('/^data: ?(.+)$/m', $body, $data)) {
            $body = $data[1];
        }

        $result = json_decode($body, true)['result'] ?? null;

        if (! is_array($result) || ! array_key_exists($expect, $result)) {
            $this->broken("{$method} answered without {$expect}");
        }

        if ($contains !== null && ! str_contains($body, '"'.$contains.'"')) {
            $this->broken("{$method} is missing {$contains}");
        }
    }

    private function step(string $name, int $expected, callable $send, int $budget = self::BUDGET): Response
    {
        $started = hrtime(true);

        try {
            /** @var Response $response */
            $response = $send();
        } catch (Throwable $e) {
            $this->rows[] = [$name, '—', '—', Str::limit($e->getMessage(), 80)];

            throw new RuntimeException("{$name}: ".$e->getMessage());
        }

        $ms = (int) round((hrtime(true) - $started) / 1_000_000);
        $this->keepCookies($response);

        $note = $response->status() === $expected ? '' : 'expected '.$expected.$this->oauthError($response);
        $note = $note === '' && $ms > $budget ? "slower than the assistant waits ({$budget} ms)" : $note;
        $this->rows[] = [$name, (string) $response->status(), "{$ms} ms", $note ?: 'ok'];

        if ($note !== '') {
            throw new RuntimeException("{$name}: {$note}");
        }

        return $response;
    }

    private function oauthError(Response $response): string
    {
        $error = $response->json('error');
        $why = $response->json('error_description') ?? $response->json('message');

        return $error || $why ? ' ('.trim(($error ? $error.': ' : '').Str::limit((string) $why, 80)).')' : '';
    }

    private function http(): PendingRequest
    {
        $request = Http::withoutRedirecting()->timeout(35)->withUserAgent('ReviseMy-ConnectProbe/1.0');

        return $this->cookies === [] ? $request : $request->withHeaders([
            'Cookie' => collect($this->cookies)->map(fn ($value, $name) => $name.'='.$value)->implode('; '),
        ]);
    }

    /** The browser part needs a session cookie; nothing else is kept. */
    private function keepCookies(Response $response): void
    {
        $uri = $response->effectiveUri();

        if ($uri !== null && $uri->getHost() !== $this->host) {
            return;
        }

        foreach ($response->headers()['Set-Cookie'] ?? [] as $cookie) {
            [$pair] = explode(';', (string) $cookie, 2);
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if ($value === '' || $value === 'deleted') {
                unset($this->cookies[trim($name)]);
            } else {
                $this->cookies[trim($name)] = trim($value);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function initializeParams(): array
    {
        return [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'revisemy-connect-probe', 'version' => '1.0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params): array
    {
        return ['jsonrpc' => '2.0', 'id' => random_int(1, 1_000_000), 'method' => $method, 'params' => (object) $params];
    }

    private function broken(string $why): never
    {
        throw new RuntimeException($why);
    }
}
