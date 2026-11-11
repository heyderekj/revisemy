<?php

namespace Tests\Feature;

use Tests\TestCase;

class SkillsPageTest extends TestCase
{
    public function test_the_index_groups_every_pack_by_category(): void
    {
        $response = $this->get('/skills')->assertOk()
            ->assertSee('npx skills add heyderekj/revisemy', false);

        foreach (config('skills.categories') as $key => $category) {
            $response->assertSee($category['label']);
            if ($category['tab'] ?? false) {
                $response->assertSee('id="panel-'.$key.'"', false);
            }
        }
        $response->assertSee('role="tablist"', false);
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

        foreach (array_column($page['features'], 'title') as $skill) {
            $this->assertFileExists(base_path("plugin/skills/{$skill}/SKILL.md"));
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

    public function test_community_skills_credit_their_authors_and_link_to_the_original_source(): void
    {
        $html = $this->get('/skills')->assertOk()->assertSee('Community skills');
        $markdown = $this->get('/skills.md')->assertOk()->assertSee('## Community skills');

        foreach (config('skills.community') as $skill) {
            $html->assertSee($skill['author'])->assertSee('href="'.$skill['source'].'"', false);
            $markdown->assertSee($skill['author'])->assertSee($skill['source'], false);
            $html->assertSee($skill['prompt']);
            $markdown->assertSee($skill['prompt']);
            if (isset($skill['install'])) {
                $html->assertSee($skill['install']);
                $markdown->assertSee($skill['install']);
            }
        }

        $html->assertSee('Get the skill from Shift Nudge');
        $markdown->assertSee('Shift Nudge sends the file by email.');
    }

    public function test_the_example_shows_how_to_request_feedback_and_verify_a_fix(): void
    {
        $example = config('skills.review_example');
        foreach (['/skills', '/skills.md'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee($example['request'])
                ->assertSee($example['mark'])
                ->assertSee($example['verify']);
        }
    }

    public function test_the_app_packs_have_installable_skills(): void
    {
        foreach (['product-flow' => 'review-product-flow', 'prototype-review' => 'review-prototypes'] as $pack => $skill) {
            $this->get("/skills/{$pack}")->assertOk()->assertSee($skill);
            $this->get("/skills/{$pack}.md")->assertOk()->assertSee($skill);
            $this->assertFileExists(base_path("plugin/skills/{$skill}/SKILL.md"));
        }
    }

    public function test_an_unknown_pack_or_a_link_only_entry_is_not_a_page(): void
    {
        $this->get('/skills/nope')->assertNotFound();
        $this->get('/skills/checkup')->assertNotFound();
    }
}
