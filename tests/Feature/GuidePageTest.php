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
            ->assertSee('The problem', false)
            ->assertSee('How ReviseMy fits', false)
            ->assertSee('ChatGPT', false)
            ->assertSee('Cursor', false)
            ->assertSee('Ask agent', false)
            ->assertSee('Hosts', false)
            ->assertSee('Developer → Edit Config', false)
            ->assertSee('Checkup prompt', false);
    }

    public function test_second_opinion_page_returns_success(): void
    {
        $page = config('guides.pages.second-opinion');

        $this->get('/second-opinion')
            ->assertOk()
            ->assertSee($page['headline'], false)
            ->assertSee($page['title'], false)
            ->assertSee('The problem', false)
            ->assertSee('How ReviseMy fits', false)
            ->assertSee('create_review', false)
            ->assertSee('How it stays non-contradictory', false)
            ->assertSee('Where the craft lenses come from', false)
            ->assertSee('IIDS', false)
            ->assertSee('Laws of UX', false)
            ->assertSee('lawsofux.com', false)
            ->assertSee('A List Apart', false)
            ->assertSee('alistapart.com', false)
            ->assertDontSee('animations.dev', false)
            ->assertDontSee('Design engineering', false)
            ->assertDontSee('Fluid interfaces', false)
            ->assertSee('Good Email Code', false)
            ->assertSee('UI craft', false)
            ->assertSee('Are these designers reviewing my UI?', false);
    }
}
