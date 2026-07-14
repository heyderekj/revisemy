<?php

namespace Tests\Feature;

use Tests\TestCase;

class AlternativePageTest extends TestCase
{
    public function test_alternatives_hub_lists_all_pages(): void
    {
        $response = $this->get('/alternatives');

        $response->assertOk()
            ->assertSee('Thoughtful comparisons', false);

        foreach (config('alternatives.pages', []) as $page) {
            $response->assertSee($page['label'], false)
                ->assertSee('/alternatives/'.$page['slug'], false);
        }
    }

    public function test_each_alternative_page_returns_success(): void
    {
        foreach (config('alternatives.pages', []) as $slug => $page) {
            $response = $this->get('/alternatives/'.$slug)
                ->assertOk()
                ->assertSee($page['headline'], false)
                ->assertSee($page['title'], false)
                ->assertSee('Recommended', false)
                ->assertSee('ReviseMy', false)
                ->assertSee('Competitor alternative', false);

            $links = $page['competitor_links'] ?? [];
            if ($links === [] && ! empty($page['competitor_url']) && ! empty($page['competitor_link'])) {
                $links = [[
                    'label' => $page['competitor_link'],
                    'url' => $page['competitor_url'],
                ]];
            }

            foreach ($links as $link) {
                $response
                    ->assertSee($link['url'], false)
                    ->assertSee('>'.$link['label'].'</a>', false);
            }
        }
    }

    public function test_unknown_alternative_slug_returns_not_found(): void
    {
        $this->get('/alternatives/not-a-real-tool')->assertNotFound();
    }

    public function test_sitemap_includes_alternatives_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertSee('/alternatives</loc>', false);

        foreach (config('alternatives.pages', []) as $page) {
            $response->assertSee($page['path'], false);
        }
    }

    public function test_llms_txt_includes_alternatives_section(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk()
            ->assertSee('## Alternatives', false)
            ->assertSee('/alternatives', false);

        foreach (config('alternatives.pages', []) as $page) {
            $response->assertSee($page['path'], false)
                ->assertSee($page['label'], false);
        }
    }
}
