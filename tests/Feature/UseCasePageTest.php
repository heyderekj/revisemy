<?php

namespace Tests\Feature;

use Tests\TestCase;

class UseCasePageTest extends TestCase
{
    public function test_use_case_pages_return_success_with_expected_content(): void
    {
        foreach (config('use-cases.pages', []) as $slug => $page) {
            $this->get("/for/{$slug}")
                ->assertOk()
                ->assertSee($page['headline'], false)
                ->assertSee($page['title'], false)
                ->assertSee($page['label'], false)
                ->assertSee('How to get pixels in', false)
                ->assertSee('Recommended', false);
        }
    }

    public function test_audience_pages_return_success(): void
    {
        foreach (config('use-cases.audiences', []) as $slug => $page) {
            $this->get("/for/{$slug}")
                ->assertOk()
                ->assertSee($page['headline'], false)
                ->assertSee($page['title'], false)
                ->assertSee($page['label'], false)
                ->assertDontSee('How to get pixels in', false);
        }
    }

    public function test_for_hub_lists_review_types_and_audiences(): void
    {
        $response = $this->get('/for')
            ->assertOk()
            ->assertSee('Anything visual, anyone in the loop', false);

        foreach (config('use-cases.pages', []) + config('use-cases.audiences', []) + config('use-cases.audience_notes', []) as $page) {
            $response->assertSee($page['label'], false);
        }

        foreach (array_keys(config('use-cases.audience_notes', [])) as $slug) {
            $response->assertSee('id="'.$slug.'"', false);
        }
    }

    public function test_folded_pages_redirect_to_where_their_content_lives(): void
    {
        $this->get('/for/claude')->assertRedirect('/connectors#claude')->assertStatus(301);
        $this->get('/for/copilot')->assertRedirect('/connectors#vscode');
        $this->get('/for/grok')->assertRedirect('/connectors#grok');
        $this->get('/for/designers')->assertRedirect('/for#designers')->assertStatus(301);
        $this->get('/for/founders')->assertRedirect('/for#founders');
        $this->get('/mcp-apps')->assertRedirect('/connectors#mcp-apps')->assertStatus(301);
        $this->get('/webhooks')->assertRedirect('/connectors#webhooks')->assertStatus(301);
    }

    public function test_unknown_use_case_slug_returns_not_found(): void
    {
        $this->get('/for/unknown-use-case')->assertNotFound();
    }

    public function test_sitemap_includes_use_case_and_discovery_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertSee('/for</loc>', false)
            ->assertSee('/connectors', false)
            ->assertSee('/second-opinion', false)
            ->assertSee('/board', false)
            ->assertSee('/guest-links', false)
            ->assertSee('/changelog', false)
            ->assertSee('/privacy', false)
            ->assertSee('/terms', false)
            ->assertSee('/alternatives</loc>', false);

        foreach (array_keys(config('use-cases.pages', [])) as $slug) {
            $response->assertSee("/for/{$slug}", false);
        }

        foreach (array_keys(config('use-cases.audiences', [])) as $slug) {
            $response->assertSee("/for/{$slug}", false);
        }

    }

    public function test_llms_txt_includes_discovery_pages(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk()
            ->assertSee('## Use cases', false)
            ->assertSee('/connectors', false)
            ->assertSee('/second-opinion', false)
            ->assertSee('/board', false)
            ->assertSee('/guest-links', false)
            ->assertSee('/changelog', false)
            ->assertSee('/privacy', false)
            ->assertSee('/terms', false)
            ->assertSee('## Alternatives', false)
            ->assertSee('/alternatives', false);

        foreach (config('use-cases.pages', []) as $slug => $page) {
            $response->assertSee("/for/{$slug}", false)
                ->assertSee($page['label'], false);
        }

        foreach (config('use-cases.audiences', []) as $slug => $page) {
            $response->assertSee("/for/{$slug}", false)
                ->assertSee($page['label'], false);
        }

    }
}
