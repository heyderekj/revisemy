<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Try tokens are unauthenticated in practice — the homepage hands them out —
 * so any URL the server fetches on a caller's behalf must not be able to reach
 * loopback, the private network, or the cloud metadata endpoint.
 */
class OutboundUrlGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUpEnv(): string
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);
        Queue::fake();

        return $this->postJson('/api/try-token')->json('token');
    }

    public function test_create_review_rejects_the_cloud_metadata_endpoint(): void
    {
        $token = $this->setUpEnv();

        Http::fake();

        $this->withToken($token)
            ->postJson('/api/reviews', [
                'title' => 'Metadata probe',
                'images' => ['http://169.254.169.254/latest/meta-data/'],
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, Review::query()->count());
    }

    public function test_create_review_rejects_loopback_and_private_hosts(): void
    {
        $token = $this->setUpEnv();

        Http::fake();

        foreach ([
            'http://127.0.0.1:6379/',
            'http://localhost/admin',
            'http://10.0.0.5/internal.png',
            'http://192.168.1.1/shot.png',
            'http://[::1]/shot.png',
        ] as $url) {
            $this->withToken($token)
                ->postJson('/api/reviews', ['title' => 'Probe', 'images' => [$url]])
                ->assertStatus(422);
        }

        Http::assertNothingSent();
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_redirect_to_loopback_is_not_followed(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'cdn.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret.png']),
            '127.0.0.1/*' => Http::response('should never be requested', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->withToken($token)
            ->postJson('/api/reviews', [
                'title' => 'Redirect probe',
                'images' => ['https://cdn.test/shot.png'],
            ])
            ->assertStatus(422);

        Http::assertSent(fn ($request) => $request->url() === 'https://cdn.test/shot.png');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
        $this->assertSame(0, Review::query()->count());
    }

    public function test_a_redirect_to_an_allowed_host_is_followed(): void
    {
        $token = $this->setUpEnv();

        $png = hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        );

        Http::fake([
            'cdn.test/shot.png' => Http::response('', 302, ['Location' => 'https://images.test/real.png']),
            'images.test/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->withToken($token)
            ->postJson('/api/reviews', [
                'title' => 'Redirect follow',
                'images' => ['https://cdn.test/shot.png'],
            ])
            ->assertStatus(201);

        $this->assertSame(1, Review::query()->count());
    }

    public function test_oversized_responses_are_rejected_by_content_length(): void
    {
        $token = $this->setUpEnv();

        Http::fake([
            'images.test/*' => Http::response('x', 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => (string) (9 * 1024 * 1024),
            ]),
        ]);

        $this->withToken($token)
            ->postJson('/api/reviews', [
                'title' => 'Too big',
                'images' => ['https://images.test/huge.png'],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Review::query()->count());
    }

    public function test_guard_rejects_non_http_schemes_and_odd_ports(): void
    {
        $this->assertNotNull(OutboundUrl::reasonToReject('file:///etc/passwd'));
        $this->assertNotNull(OutboundUrl::reasonToReject('gopher://example.com/'));
        $this->assertNotNull(OutboundUrl::reasonToReject('https://example.com:6379/'));
        $this->assertNotNull(OutboundUrl::reasonToReject('not a url'));
    }

    public function test_guard_allows_ordinary_public_https_urls(): void
    {
        // Unresolvable hosts are allowed — the request just fails on its own.
        $this->assertNull(OutboundUrl::reasonToReject('https://images.test/shot.png'));
        $this->assertNull(OutboundUrl::reasonToReject('https://images.test:443/shot.png'));
    }
}
