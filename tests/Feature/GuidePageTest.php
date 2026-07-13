<?php

namespace Tests\Feature;

use Tests\TestCase;

class GuidePageTest extends TestCase
{
    public function test_connectors_page_returns_success(): void
    {
        $page = config('guides.pages.connectors');

        $this->get('/connectors')
            ->assertOk()
            ->assertSee($page['headline'], false)
            ->assertSee($page['title'], false)
            ->assertSee('ChatGPT', false)
            ->assertSee('Cursor', false)
            ->assertSee('Hosts', false);
    }

    public function test_second_opinion_page_returns_success(): void
    {
        $page = config('guides.pages.second-opinion');

        $this->get('/second-opinion')
            ->assertOk()
            ->assertSee($page['headline'], false)
            ->assertSee($page['title'], false)
            ->assertSee('How it stays non-contradictory', false);
    }
}
