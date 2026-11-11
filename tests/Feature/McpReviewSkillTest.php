<?php

namespace Tests\Feature;

use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class McpReviewSkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_agents_can_discover_the_tool_and_list_the_supported_skills(): void
    {
        Http::fake();
        $this->withToken(app(TryTokenService::class)->create()['token']);

        $this->postJson('/mcp/revisemy', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'get_review_skill']);

        $this->getSkill()
            ->assertJsonPath('result.structuredContent.skills.0.id', 'prototype')
            ->assertJsonPath('result.structuredContent.skills.0.author', 'Emil Kowalski')
            ->assertJsonPath('result.structuredContent.skills.0.review_focus', 'variants')
            ->assertJsonCount(4, 'result.structuredContent.skills');

        $this->getSkill(['skill' => 'https://example.com/skill'])->assertJsonPath('result.isError', true);
        $this->getSkill(['skill' => 'better-writing', 'focus' => 'motion'])->assertJsonPath('result.isError', true);
        Http::assertNothingSent();
    }

    public function test_it_returns_the_authors_verified_instructions_and_caches_them(): void
    {
        $this->withToken(app(TryTokenService::class)->create()['token']);
        $markdown = "# Writing review\nRead the words on the screen.\n";
        $blob = sha1('blob '.strlen($markdown)."\0".$markdown);
        $catalog = config('skills.community');
        foreach ($catalog as &$entry) {
            if (($entry['mcp']['id'] ?? null) === 'better-writing') {
                $entry['mcp']['blob'] = $blob;
            }
        }
        unset($entry);
        config(['skills.community' => $catalog]);
        Http::preventStrayRequests();
        Http::fake([
            "https://api.github.com/repos/jakubkrehel/skills/git/blobs/{$blob}" => Http::response([
                'encoding' => 'base64',
                'content' => base64_encode($markdown),
            ]),
        ]);

        foreach ([['focus' => 'copy'], ['skill' => 'Better Writing']] as $arguments) {
            $this->getSkill($arguments)
                ->assertJsonPath('result.structuredContent.id', 'better-writing')
                ->assertJsonPath('result.structuredContent.author', 'Jakub Krehel')
                ->assertJsonPath('result.structuredContent.upstream_status', 'loaded')
                ->assertJsonPath('result.structuredContent.upstream_instructions', $markdown)
                ->assertJsonPath('result.structuredContent.upstream_license', fn ($license) => str_contains($license, 'Copyright (c) 2026 Jakub Krehel'))
                ->assertJsonPath('result.structuredContent.next_steps.0.tool', 'get_review')
                ->assertJsonPath('result.structuredContent.next_steps.1.tool', 'create_review')
                ->assertJsonPath('result.structuredContent.next_steps.2.tool', 'add_findings');
        }

        Http::assertSentCount(1);
    }

    public function test_an_inferred_focus_returns_revisemy_guidance_for_opt_in_skills(): void
    {
        $this->withToken(app(TryTokenService::class)->create()['token']);
        Http::fake();

        foreach (['motion' => 'review-animations', 'variants' => 'prototype'] as $focus => $skill) {
            $this->getSkill(['focus' => $focus])
                ->assertJsonPath('result.structuredContent.id', $skill)
                ->assertJsonPath('result.structuredContent.upstream_status', 'explicit_only')
                ->assertJsonPath('result.structuredContent.upstream_instructions', null)
                ->assertJsonPath('result.structuredContent.review_workflow', fn ($workflow) => is_string($workflow) && $workflow !== '');
        }

        Http::assertNothingSent();
    }

    public function test_it_falls_back_to_revisemy_guidance_if_upstream_content_cannot_be_verified(): void
    {
        $this->withToken(app(TryTokenService::class)->create()['token']);
        Http::fakeSequence('https://api.github.com/*')
            ->push(['encoding' => 'base64', 'content' => base64_encode('unexpected instructions')])
            ->pushStatus(503);

        foreach (['review-animations', 'prototype'] as $skill) {
            $this->getSkill(['skill' => $skill])
                ->assertJsonPath('result.structuredContent.upstream_status', 'unavailable')
                ->assertJsonPath('result.structuredContent.upstream_instructions', null)
                ->assertJsonPath('result.structuredContent.review_workflow', fn ($workflow) => is_string($workflow) && $workflow !== '');
        }
    }

    private function getSkill(array $arguments = []): TestResponse
    {
        return $this->postJson('/mcp/revisemy', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'get_review_skill', 'arguments' => $arguments],
        ])->assertOk();
    }
}
