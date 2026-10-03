<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_browsable_page_carries_the_site_nav(): void
    {
        foreach (['/board', '/for', '/for/websites', '/alternatives', '/alternatives/marker-io', '/connectors', '/privacy', '/terms', '/reviews'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('aria-label="Site"', false)
                ->assertSee('href="/#how"', false)
                ->assertSee('href="/connectors"', false);
        }
    }

    public function test_the_homepage_nav_jumps_within_the_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('On this page')
            ->assertSee('href="#how"', false)
            ->assertDontSee('href="/#how"', false);
    }

    public function test_the_current_section_is_marked(): void
    {
        $this->get('/for/websites')
            ->assertSee('href="/for"', false)
            ->assertSee('aria-current="page"', false);

        $this->get('/alternatives/marker-io')
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_review_connect_and_billing_pages_stay_bare(): void
    {
        Storage::fake('public');
        $try = app(TryTokenService::class)->create();
        $png = 'data:image/png;base64,'.base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));
        $this->withToken($try['token'])->postJson('/api/reviews', ['title' => 'Hero', 'images' => [$png]])->assertCreated();
        $review = Review::latest('id')->firstOrFail();

        $this->get('/r/'.$review->token)->assertOk()->assertDontSee('aria-label="Site"', false);
        $this->get('/connect')->assertOk()->assertDontSee('aria-label="Site"', false);
    }
}
