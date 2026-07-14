<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPageTest extends TestCase
{
    public function test_privacy_page_returns_success(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Privacy', false)
            ->assertSee('Try workspaces', false)
            ->assertSee('not lawyer-reviewed', false);
    }

    public function test_terms_page_returns_success(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('Terms', false)
            ->assertSee('MIT License', false)
            ->assertSee('as is', false);
    }
}
