<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenSourceSectionTest extends TestCase
{
    public function test_the_repo_mark_shows_the_star_count(): void
    {
        Http::fake(['api.github.com/repos/heyderekj/revisemy' => Http::response(['stargazers_count' => 1234])]);

        $this->get('/')
            ->assertOk()
            ->assertSee('1,234')
            ->assertSee('stars so far');
    }

    public function test_the_page_leaves_the_count_off_when_github_is_unreachable(): void
    {
        Http::fake(['api.github.com/*' => Http::response(null, 503)]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Star on GitHub')
            ->assertDontSee('stars so far');
    }
}
