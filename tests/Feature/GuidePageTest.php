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
            ->assertSee('Connect your assistant', false)
            ->assertSee('Connect ChatGPT', false)
            ->assertSee('Connect Muse', false)
            ->assertSee('Add to Cursor', false)
            ->assertDontSee('Developer → Edit Config', false);
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
            ->assertSee('How it stays straight', false)
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

    public function test_board_page_returns_success(): void
    {
        $page = config('guides.pages.board');

        $this->get('/board')
            ->assertOk()
            ->assertSee($page['headline'], false)
            ->assertSee($page['title'], false)
            ->assertSee('The problem', false)
            ->assertSee('How ReviseMy fits', false)
            ->assertSee('resolve_marks', false)
            ->assertSee('How status stays honest', false)
            ->assertSee('open', false)
            ->assertSee('verified', false)
            ->assertSee($page['faq'][3]['q'], false);
    }

    public function test_guest_links_page_returns_success(): void
    {
        $page = config('guides.pages.guest-links');

        $this->get('/guest-links')
            ->assertOk()
            ->assertSee($page['headline'], false)
            ->assertSee($page['title'], false)
            ->assertSee('guest link', false)
            ->assertSee('G#', false)
            ->assertSee('share_token', false);
    }

    public function test_connectors_covers_webhooks_and_inline_review(): void
    {
        $this->get('/connectors')
            ->assertOk()
            ->assertSee('id="webhooks"', false)
            ->assertSee('review.decided', false)
            ->assertSee('X-ReviseMy-Signature', false)
            ->assertSee('id="mcp-apps"', false)
            ->assertSee('Reviews right in the chat', false);
    }

    public function test_changelog_page_returns_success(): void
    {
        $page = config('guides.pages.changelog');
        $version = config('revisemy.version');
        $entry = config('changelog.entries.0');

        $this->get('/changelog')
            ->assertOk()
            ->assertSee($page['headline'], false)
            ->assertSee($page['title'], false)
            ->assertSee('v'.$version, false)
            ->assertSee('v'.$entry['version'], false)
            ->assertSee($entry['title'], false)
            ->assertDontSee('The problem', false)
            ->assertDontSee('How ReviseMy fits', false);
    }
}
