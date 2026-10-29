<?php

namespace Tests\Feature;

use Tests\TestCase;

class SkillsPageTest extends TestCase
{
    public function test_the_index_groups_every_pack_by_category(): void
    {
        $response = $this->get('/skills')->assertOk()
            ->assertSee('npx skills add heyderekj/revisemy', false);

        foreach (config('skills.categories') as $category) {
            $response->assertSee($category['label']);
        }
        foreach (config('skills.packs') as $pack) {
            $response->assertSee('href="'.($pack['path'] ?? $pack['href']).'"', false);
        }

        $this->get('/skills.md')->assertOk()
            ->assertSee('/skills/homepage.md', false)
            ->assertSee('/connectors#claude-code-plugin)', false);
    }

    public function test_the_homepage_pack_lists_its_skills(): void
    {
        $page = config('skills.packs.homepage');
        $html = $this->get('/skills/homepage')->assertOk()->assertSee($page['headline'])->getContent();
        $markdown = $this->get('/skills/homepage.md')->assertOk()->getContent();

        foreach (glob(base_path('plugin/skills/*'), GLOB_ONLYDIR) as $dir) {
            $skill = basename($dir);
            if (in_array($skill, ['design-checkup', 'faith-homepage'], true)) {
                continue;
            }

            $this->assertStringContainsString($skill, $html, "/skills/homepage is missing {$skill}.");
            $this->assertStringContainsString($skill, $markdown, "/skills/homepage.md is missing {$skill}.");
        }
    }

    public function test_the_faith_pack_has_its_own_page(): void
    {
        $this->get('/skills/faith')->assertOk()
            ->assertSee(config('skills.packs.faith.headline'))
            ->assertSee('faith-homepage');
        $this->get('/skills/faith.md')->assertOk()->assertSee('faith-homepage');
    }

    public function test_an_unknown_pack_or_a_link_only_entry_is_not_a_page(): void
    {
        $this->get('/skills/nope')->assertNotFound();
        $this->get('/skills/checkup')->assertNotFound();
    }
}
