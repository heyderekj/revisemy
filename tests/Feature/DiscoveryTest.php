<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Support\McpCatalog;
use App\Support\PageMarkdown;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    public function test_the_catalog_lists_what_an_agent_can_call(): void
    {
        config(['billing.pricing_enabled' => true]);
        $names = McpCatalog::toolNames();

        $this->assertContains('resolve_marks', $names);
        $this->assertContains('create_checkout', $names);
        foreach (['add_mark', 'decide_review', 'verify_mark'] as $humanOnly) {
            $this->assertNotContains($humanOnly, $names);
        }

        config(['billing.pricing_enabled' => false]);
        $this->assertNotContains('create_checkout', McpCatalog::toolNames());
        $this->assertContains('get_billing', McpCatalog::toolNames());
    }

    public function test_every_next_action_the_review_returns_is_documented(): void
    {
        preg_match_all("/'action' => '([a-z_]+)'/", file_get_contents(app_path('Models/Review.php')), $matches);

        $this->assertNotEmpty($matches[1]);
        foreach (array_unique($matches[1]) as $action) {
            $this->assertArrayHasKey($action, Review::NEXT_ACTIONS);
        }
    }

    public function test_llms_txt_lists_exactly_the_catalog_tools(): void
    {
        $body = $this->get('/llms.txt')->assertOk()->getContent();

        preg_match_all('/^- `([a-z_]+)` — /m', substr($body, strpos($body, '### MCP tools'), strpos($body, '### Prompts') - strpos($body, '### MCP tools')), $listed);
        $this->assertSame(McpCatalog::toolNames(), $listed[1]);

        $this->assertStringContainsString('`design_checkup_loop`', $body);
        $this->assertStringContainsString('`apply_decision_note`', $body);
        $this->assertStringContainsString('/board.md', $body);
        $this->assertStringContainsString('/.well-known/mcp/server-card.json', $body);
    }

    public function test_every_public_page_has_a_markdown_twin(): void
    {
        $full = $this->get('/llms-full.txt')->assertOk()->getContent();

        foreach (PageMarkdown::paths() as $path) {
            $url = $path === '/' ? '/index.md' : $path.'.md';
            $markdown = $this->get($url)
                ->assertOk()
                ->assertHeader('Content-Type', 'text/markdown; charset=utf-8')
                ->getContent();

            $heading = strtok($markdown, "\n");
            $this->assertStringStartsWith('# ', $heading, $url);
            $this->assertStringContainsString($heading, $full, $url.' is in llms-full.txt');
        }

        $this->get('/not-a-page.md')->assertNotFound();
        $this->get('/board')->assertSee('href="'.url('/board.md').'"', false);
    }

    public function test_the_server_card_matches_the_server_and_the_registry_file(): void
    {
        $card = $this->getJson('/.well-known/mcp/server-card.json')->assertOk()->json();
        $registry = json_decode(file_get_contents(base_path('server.json')), true);
        $plugin = json_decode(file_get_contents(base_path('plugin/.claude-plugin/plugin.json')), true);

        $this->assertSame(McpCatalog::endpoint(), $card['remotes'][0]['url']);
        $this->assertSame(McpCatalog::toolNames(), array_column($card['tools'], 'name'));
        $this->assertSame(config('revisemy.version'), $card['version']);
        $this->assertSame($card['name'], $registry['name']);
        $this->assertSame(config('revisemy.version'), $registry['version']);
        $this->assertSame(config('revisemy.version'), $plugin['version']);
        $this->assertLessThanOrEqual(100, mb_strlen($registry['description']));

        $this->getJson('/.well-known/mcp.json')->assertOk()->assertJsonPath('name', $card['name']);
    }

    public function test_the_plugin_skill_only_names_real_tools_and_actions(): void
    {
        $skill = file_get_contents(base_path('plugin/skills/design-checkup/SKILL.md'));
        config(['billing.pricing_enabled' => true]);
        $tools = McpCatalog::toolNames();

        preg_match_all('/`((?:create|get|list|add|resolve|request|cancel)_[a-z_]+)`/', $skill, $named);
        $this->assertNotEmpty($named[1]);
        foreach (array_unique($named[1]) as $tool) {
            $this->assertContains($tool, $tools, "The skill names {$tool}, which the server doesn't offer.");
        }

        foreach (array_keys(Review::NEXT_ACTIONS) as $action) {
            if ($action !== 'expired') {
                $this->assertStringContainsString('`'.$action.'`', $skill);
            }
        }
    }

    public function test_structured_data_marks_up_questions_breadcrumbs_and_the_license(): void
    {
        $home = $this->jsonLd('/');
        $types = array_column($home, '@type');
        $this->assertContains('FAQPage', $types);
        $software = collect($home)->firstWhere('@type', 'SoftwareApplication');
        $this->assertSame('https://osaasy.dev/', $software['license']);
        $this->assertStringContainsString('resolve_marks', end($software['featureList']));

        $page = $this->jsonLd('/alternatives/marker-io');
        $crumbs = collect($page)->firstWhere('@type', 'BreadcrumbList');
        $this->assertSame(['ReviseMy', 'Alternatives', 'Marker.io'], array_column($crumbs['itemListElement'], 'name'));
        $this->assertContains('FAQPage', array_column($page, '@type'));
    }

    public function test_the_canonical_names_the_main_host(): void
    {
        config(['app.url' => 'https://revisemy.com']);

        $this->get('http://preview.example.test/board')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://revisemy.com/board">', false);
    }

    public function test_the_sitemap_lists_only_indexable_pages_with_a_date(): void
    {
        $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

        foreach ($xml->url as $entry) {
            $this->assertNotEmpty((string) $entry->lastmod);
            $path = parse_url((string) $entry->loc, PHP_URL_PATH) ?: '/';
            $response = $this->get($path)->assertOk();
            $this->assertNull($response->headers->get('X-Robots-Tag'), "{$path} is in the sitemap but asks not to be indexed.");
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function jsonLd(string $path): array
    {
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $this->get($path)->getContent(), $match);

        return json_decode($match[1], true)['@graph'];
    }
}
