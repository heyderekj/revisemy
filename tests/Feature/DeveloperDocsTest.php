<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Support\DeveloperDocs;
use App\Support\McpCatalog;
use Tests\TestCase;

class DeveloperDocsTest extends TestCase
{
    public function test_every_doc_renders_in_the_site_shell_with_a_markdown_twin(): void
    {
        $pages = DeveloperDocs::pages();

        $this->assertSame('index', $pages[0]['slug']);
        $this->assertGreaterThanOrEqual(7, count($pages));

        foreach ($pages as $page) {
            $this->get($page['path'])
                ->assertOk()
                ->assertSee('aria-label="Site"', false)
                ->assertSee('<h1', false)
                ->assertSee('rel="alternate" type="text/markdown" href="'.url($page['path']).'.md"', false);

            $this->get($page['path'].'.md')
                ->assertOk()
                ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
                ->assertSee('# '.$page['title'], false);
        }
    }

    public function test_unknown_docs_are_not_found(): void
    {
        $this->get('/docs/nope')->assertNotFound();
        $this->get('/docs/index')->assertNotFound();
        $this->get('/docs/nope.md')->assertNotFound();
        $this->get('/docs/index.md')->assertNotFound();
    }

    public function test_the_tool_reference_is_exactly_what_an_agent_can_call(): void
    {
        config(['billing.pricing_enabled' => false]);

        $markdown = DeveloperDocs::markdown('mcp');

        foreach (McpCatalog::toolNames() as $tool) {
            $this->assertStringContainsString("### `{$tool}`", $markdown);
        }
        foreach (['add_mark', 'decide_review', 'verify_mark', 'create_checkout'] as $hidden) {
            $this->assertStringNotContainsString("### `{$hidden}`", $markdown);
        }

        // Parameters come from the tool's own schema.
        $this->assertStringContainsString('| `webhook_url` | string | No |', $markdown);
        $this->assertStringContainsString('| `title` | string | Yes |', $markdown);
        $this->assertStringNotContainsString('<!-- generated', $markdown);
    }

    public function test_the_review_loop_lists_every_next_action_and_cost(): void
    {
        $markdown = DeveloperDocs::markdown('review-loop');

        foreach (array_keys(Review::NEXT_ACTIONS) as $action) {
            $this->assertStringContainsString('| `'.$action.'` |', $markdown);
        }
        foreach (config('billing.costs') as $source => $credits) {
            $this->assertStringContainsString('`'.$source.'`', $markdown);
        }
        $this->assertStringNotContainsString('<!-- generated', $markdown);
    }

    public function test_examples_name_the_app_they_are_served_from(): void
    {
        config(['app.url' => 'https://review.example.test']);

        $this->assertStringContainsString('https://review.example.test/api/try-token', DeveloperDocs::markdown('quickstart'));
        $this->assertStringNotContainsString('https://revisemy.com/api', DeveloperDocs::markdown('quickstart'));
    }

    public function test_the_page_lists_the_docs_and_its_own_sections(): void
    {
        $this->get('/docs/webhooks')
            ->assertOk()
            ->assertSee('href="#checking-the-signature"', false)
            ->assertSee('id="checking-the-signature"', false)
            ->assertSee('href="/docs/rest-api"', false)
            ->assertSee('blob/main/docs/developers/webhooks.md', false);
    }

    public function test_the_docs_are_listed_for_search_engines_and_agents(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/docs/webhooks'), false);
        $this->get('/llms.txt')->assertOk()->assertSee(url('/docs/mcp').'.md', false);
    }

    public function test_the_open_source_section_and_nav_link_the_docs(): void
    {
        $this->get('/')->assertOk()->assertSee('href="/docs"', false)->assertSeeText('developer docs');
    }
}
