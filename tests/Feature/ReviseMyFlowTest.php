<?php

namespace Tests\Feature;

use App\Jobs\GenerateSecondOpinionJob;
use App\Models\Finding;
use App\Models\Review;
use App\Models\User;
use App\Services\SecondOpinionService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class ReviseMyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPngDataUrl(): string
    {
        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c63000100000500010d0a2db40000000049454e44ae426082'
        ));

        return 'data:image/png;base64,'.$png;
    }

    public function test_homepage_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Visual feedback.');
    }

    public function test_try_token_create_review_and_open_secret_link(): void
    {
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public']);

        $tokenResponse = $this->postJson('/api/try-token')->assertCreated();
        $token = $tokenResponse->json('token');

        $tokenResponse
            ->assertJsonStructure([
                'token_expires_at',
                'setup_prompts' => ['chatgpt', 'claude_desktop', 'claude_code', 'copilot', 'cursor', 'grok'],
                'checkup_prompts' => ['chatgpt', 'cursor'],
            ]);
        $this->assertNotEmpty($tokenResponse->json('token_expires_at'));
        $this->assertTrue(
            now()->diffInDays(\Illuminate\Support\Carbon::parse($tokenResponse->json('token_expires_at')), false) >= 6
        );
        $this->assertStringContainsString('mcp-remote', (string) data_get($tokenResponse->json(), 'setup_prompts.claude_desktop'));
        $this->assertStringContainsString('~/.cursor/mcp.json', (string) data_get($tokenResponse->json(), 'setup_prompts.cursor'));

        $reviewResponse = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Hero pass',
            'context' => 'Check the CTA',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated();

        $reviewResponse
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('status_label', 'Waiting on your eye');

        $this->assertStringContainsString('Apply human marks first', (string) $reviewResponse->json('guidance'));

        $url = $reviewResponse->json('review_url');
        $this->assertStringContainsString('/r/', $url);

        $this->get($url)->assertOk()->assertSee('Hero pass');

        $review = Review::query()->firstOrFail();
        $this->assertTrue($review->isOpenForFeedback());
        $this->assertNotNull($review->expires_at);
    }

    public function test_creating_a_review_seeds_checklist_without_a_queue_worker(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => true,
            'revisemy.openai.api_key' => null,
            'revisemy.openai.base_url' => null,
            'revisemy.anthropic.api_key' => null,
            'revisemy.vision.provider' => 'auto',
        ]);
        Queue::fake();

        $token = $this->postJson('/api/try-token')->json('token');

        $payload = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Queue check',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        Queue::assertNothingPushed();
        $this->assertNotEmpty($payload['work_packets']['second_opinion']);
        $this->assertSame('ready', $payload['screenshots'][0]['second_opinion_status']);
    }

    public function test_vision_provider_schedules_enrichment_after_response(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => true,
            'revisemy.vision.provider' => 'openai',
            'revisemy.openai.api_key' => 'sk-test',
            'revisemy.openai.base_url' => null,
        ]);
        \Illuminate\Support\Facades\Bus::fake();

        $token = $this->postJson('/api/try-token')->json('token');

        $payload = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Vision queue',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        \Illuminate\Support\Facades\Bus::assertDispatchedAfterResponse(GenerateSecondOpinionJob::class);
        $this->assertNotEmpty($payload['work_packets']['second_opinion']);
        $this->assertSame('queued', $payload['screenshots'][0]['second_opinion_status']);
    }

    public function test_checklist_second_opinion_writes_findings_without_changing_status(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => true,
            'revisemy.openai.api_key' => null,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');

        $payload = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'CTA review',
            'context' => 'Check the CTA contrast',
            'page_url' => 'https://example.com/demo',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        $this->assertSame('pending', $payload['status']);
        $this->assertNotEmpty($payload['work_packets']['second_opinion']);
        $this->assertSame('pending', $payload['status']);

        $sources = collect($payload['work_packets']['second_opinion'])->pluck('source')->unique()->all();
        $this->assertContains(Finding::SOURCE_CHECKLIST, $sources);

        foreach ($payload['work_packets']['second_opinion'] as $finding) {
            $this->assertContains($finding['severity'], ['suggestion', 'a11y', 'polish']);
        }
    }

    public function test_add_findings_stores_agent_suggestions(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');

        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Subagent',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $response = $this->withToken($token)->postJson('/api/reviews/'.$id.'/findings', [
            'findings' => [
                [
                    'severity' => 'a11y',
                    'body' => 'Icon button needs an accessible name.',
                    'area' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.15, 'h' => 0.1],
                    'screenshot_index' => 0,
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('status', 'pending');
        $this->assertCount(1, $response->json('work_packets.second_opinion'));
        $this->assertSame('agent', $response->json('work_packets.second_opinion.0.source'));
        $this->assertSame('a11y', $response->json('work_packets.second_opinion.0.severity'));
        $this->assertEmpty($response->json('work_packets.pins'));
    }

    public function test_accepting_a_finding_promotes_it_to_a_human_pin(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Accept finding',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $this->withToken($token)->postJson('/api/reviews/'.$id.'/findings', [
            'findings' => [
                [
                    'severity' => 'a11y',
                    'body' => 'Icon button needs an accessible name.',
                    'area' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.15, 'h' => 0.1],
                    'screenshot_index' => 0,
                ],
                [
                    'severity' => 'polish',
                    'body' => 'Tighten spacing near the footer.',
                    'screenshot_index' => 0,
                ],
            ],
        ])->assertCreated();

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $finding = $review->screenshots()->firstOrFail()->findings()->where('severity', 'a11y')->firstOrFail();
        $dismiss = $review->screenshots()->firstOrFail()->findings()->where('severity', 'polish')->firstOrFail();

        $component = Livewire::test('review-page', ['token' => $review->token])
            ->call('acceptFinding', $finding->id)
            ->call('dismissFinding', $dismiss->id);

        $component->assertOk();

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings'])->toAgentPayload();

        $this->assertCount(1, $payload['work_packets']['pins']);
        $this->assertSame('must-fix', $payload['work_packets']['pins'][0]['severity']);
        $this->assertSame('Icon button needs an accessible name.', $payload['work_packets']['pins'][0]['body']);
        $this->assertSame(0.1, $payload['work_packets']['pins'][0]['area']['x']);
        $this->assertEmpty($payload['work_packets']['second_opinion']);
        $this->assertSame(Finding::STATUS_ACCEPTED, $finding->fresh()->status);
        $this->assertSame(Finding::STATUS_DISMISSED, $dismiss->fresh()->status);
    }

    public function test_to_agent_payload_separates_pins_from_second_opinion(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Packets',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $shot->annotations()->create([
            'x' => 0.5,
            'y' => 0.5,
            'area' => ['x' => 0.4, 'y' => 0.4, 'w' => 0.2, 'h' => 0.2],
            'severity' => 'must-fix',
            'body' => 'Human says fix this',
            'number' => 1,
        ]);

        app(SecondOpinionService::class)->addAgentFindings($review, [
            [
                'severity' => 'polish',
                'body' => 'Agent hint only',
                'screenshot_index' => 0,
            ],
        ]);

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings'])->toAgentPayload();

        $this->assertCount(1, $payload['work_packets']['pins']);
        $this->assertSame('must-fix', $payload['work_packets']['pins'][0]['severity']);
        $this->assertSame(0.4, $payload['work_packets']['pins'][0]['area']['x']);
        $this->assertSame(0.2, $payload['work_packets']['pins'][0]['area']['w']);
        $this->assertCount(1, $payload['work_packets']['second_opinion']);
        $this->assertSame('agent', $payload['work_packets']['second_opinion'][0]['source']);
        $this->assertSame('pending', $payload['status']);
    }

    public function test_next_action_and_follow_up_pass(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');

        $first = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Pass 1',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        $this->assertSame('wait_for_human', $first['next_action']['action']);
        $this->assertSame(1, $first['pass']);

        $review = Review::query()->where('public_id', $first['id'])->firstOrFail();
        $review->screenshots()->firstOrFail()->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Fix the CTA', 'number' => 1,
        ]);
        $review->update([
            'status' => Review::STATUS_CHANGES_REQUESTED,
            'decision_at' => now(),
            'decision_note' => 'Fix the CTA',
        ]);

        // An outstanding mark keeps the agent on the apply-then-next-pass step.
        $payload = $review->fresh()->toAgentPayload();
        $this->assertSame('apply_pins_then_next_pass', $payload['next_action']['action']);
        $this->assertTrue($payload['next_action']['create_next_pass']);
        $this->assertFalse($review->fresh()->isOpenForFeedback());

        $second = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Pass 2',
            'parent_id' => $first['id'],
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        $this->assertSame(2, $second['pass']);
        $this->assertSame($first['id'], $second['parent_id']);
        $this->assertSame('wait_for_human', $second['next_action']['action']);
        $this->assertSame('pending', $second['status']);
    }

    public function test_guest_share_link_opens_in_guest_mode(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $payload = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Share me',
            'images' => [$this->tinyPngDataUrl()],
        ])->assertCreated()->json();

        $review = Review::query()->where('public_id', $payload['id'])->firstOrFail();

        $this->assertNotNull($review->share_token);
        $this->assertNotSame($review->token, $review->share_token);
        $this->assertStringContainsString($review->share_token, $payload['guest_share_url']);

        $guestPage = $this->get('/r/'.$review->share_token)->assertOk();
        $guestPage->assertSee('Share me');
        $guestPage->assertSee('Guest');
        $guestPage->assertDontSee('wire:click="approve"', false);
        $guestPage->assertDontSee($review->token);

        $ownerPage = $this->get('/r/'.$review->token)->assertOk();
        $ownerPage->assertSee('wire:click="approve"', false);
        // Owner header embeds the guest link for the copy-to-share control.
        $ownerPage->assertSee($review->share_token, false);
    }

    public function test_guest_pin_becomes_a_suggestion_not_a_mark(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Guest suggests',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startPin', 0.3, 0.4)
            ->set('guestName', 'Sam')
            ->set('draftBody', 'The heading feels cramped.')
            ->set('draftSeverity', 'nit')
            ->call('savePin')
            ->assertOk();

        $shot = $review->screenshots()->firstOrFail();
        $this->assertSame(0, $shot->annotations()->count());

        $finding = $shot->findings()->firstOrFail();
        $this->assertSame(Finding::SOURCE_GUEST, $finding->source);
        $this->assertSame('Sam', $finding->author);
        $this->assertSame('must-fix', $finding->severity);
        $this->assertSame('The heading feels cramped.', $finding->body);
        $this->assertEqualsWithDelta(0.3, $finding->x, 0.0001);
        $this->assertEqualsWithDelta(0.4, $finding->y, 0.0001);
        $this->assertSame(Finding::STATUS_OPEN, $finding->status);
    }

    public function test_guest_page_hides_second_opinion_section(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => true,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Guest view',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->assertDontSee('Second opinion')
            ->assertSee('Guest feedback');
    }

    public function test_guest_pin_with_invalid_name_shows_validation_error(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Guest validation',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('startPin', 0.3, 0.4)
            ->set('guestName', "Sam\n🔥")
            ->set('draftBody', 'Needs more contrast.')
            ->call('savePin')
            ->assertHasErrors(['guestName']);

        $this->assertSame(0, $review->screenshots()->firstOrFail()->findings()->count());
    }

    public function test_mark_numbers_are_unique_across_shots_in_a_review(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Multi shot numbers',
            'images' => [$this->tinyPngDataUrl(), $this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $this->assertCount(2, $review->screenshots);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('selectScreenshot', 0)
            ->call('startPin', 0.2, 0.2)
            ->set('draftBody', 'First shot mark')
            ->set('draftSeverity', 'must-fix')
            ->call('savePin')
            ->call('selectScreenshot', 1)
            ->call('startPin', 0.4, 0.4)
            ->set('draftBody', 'Second shot mark')
            ->set('draftSeverity', 'nit')
            ->call('savePin')
            ->assertOk();

        $shots = $review->screenshots()->orderBy('sort_order')->get();
        $first = $shots[0]->annotations()->firstOrFail();
        $second = $shots[1]->annotations()->firstOrFail();

        $this->assertSame(1, $first->number);
        $this->assertSame(2, $second->number);
        $this->assertSame(3, $review->fresh()->nextMarkNumber());
    }

    public function test_suggestion_numbers_are_unique_across_shots(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Multi shot suggestions',
            'images' => [$this->tinyPngDataUrl(), $this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()
            ->where('public_id', $id)
            ->with('screenshots.findings')
            ->firstOrFail();

        $shots = $review->screenshots()->orderBy('sort_order')->get();

        $s1 = $shots[0]->findings()->create([
            'source' => Finding::SOURCE_AGENT,
            'severity' => Finding::SEVERITY_A11Y,
            'body' => 'Shot one opinion',
            'status' => Finding::STATUS_OPEN,
        ]);
        $g1 = $shots[0]->findings()->create([
            'source' => Finding::SOURCE_GUEST,
            'author' => 'Sam',
            'severity' => 'nit',
            'body' => 'Shot one guest',
            'status' => Finding::STATUS_OPEN,
        ]);
        $s2 = $shots[1]->findings()->create([
            'source' => Finding::SOURCE_AGENT,
            'severity' => Finding::SEVERITY_POLISH,
            'body' => 'Shot two opinion',
            'status' => Finding::STATUS_OPEN,
        ]);
        $g2 = $shots[1]->findings()->create([
            'source' => Finding::SOURCE_GUEST,
            'author' => 'Alex',
            'severity' => 'must-fix',
            'body' => 'Shot two guest',
            'status' => Finding::STATUS_OPEN,
        ]);

        $review->load('screenshots.findings');
        $numbers = $review->suggestionDisplayNumbers();

        $this->assertSame(1, $numbers['s'][$s1->id]);
        $this->assertSame(2, $numbers['s'][$s2->id]);
        $this->assertSame(1, $numbers['g'][$g1->id]);
        $this->assertSame(2, $numbers['g'][$g2->id]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('selectScreenshot', 1)
            ->assertSee('S2', false)
            ->assertSee('G2', false)
            ->assertSee('Shot two opinion')
            ->assertSee('Shot two guest');
    }

    public function test_blur_save_title_persists_when_changed(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Original title',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();

        Livewire::test('review-page', ['token' => $review->token])
            ->call('startEditTitle')
            ->set('titleDraft', 'Updated title')
            ->call('blurSaveTitle')
            ->assertHasNoErrors();

        $this->assertSame('Updated title', $review->fresh()->title);
    }

    public function test_home_try_token_includes_agent_setup_prompts(): void
    {
        $component = Livewire::test('home')->call('getTryToken');

        $this->assertNotEmpty($component->get('token'));
        $this->assertNotEmpty($component->get('tokenExpiresAt'));
        $this->assertStringContainsString('~/.cursor/mcp.json', (string) $component->get('setupPromptsJson'));
        $this->assertStringContainsString('mcp-remote', (string) $component->get('setupPromptsJson'));
    }

    public function test_home_try_token_setup_can_be_restored_and_cleared(): void
    {
        Livewire::test('home')
            ->call('restoreTryTokenSetup', 'tok_abc', 'https://example.test/mcp', '{}', '{}', '{}', 'claude mcp add', '{"cursor":"setup"}', '{"cursor":"checkup"}', '2030-01-01T00:00:00+00:00')
            ->assertSet('token', 'tok_abc')
            ->assertSet('mcpUrl', 'https://example.test/mcp')
            ->assertSet('setupPromptsJson', '{"cursor":"setup"}')
            ->assertSet('tokenExpiresAt', '2030-01-01T00:00:00+00:00')
            ->call('clearTryTokenSetup')
            ->assertSet('token', null)
            ->assertSet('mcpUrl', null)
            ->assertSet('setupPromptsJson', null)
            ->assertSet('tokenExpiresAt', null);
    }

    public function test_home_try_token_failure_sets_error_instead_of_throwing(): void
    {
        $this->mock(TryTokenService::class, function ($mock) {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new \RuntimeException('database missing'));
        });

        Livewire::test('home')
            ->call('getTryToken')
            ->assertSet('token', null)
            ->assertSet('error', 'Could not start a free try right now. On Laravel Cloud, attach Postgres and run migrations — SQLite does not persist across deploys.');
    }

    public function test_guest_cannot_perform_owner_actions(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Locked down',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $pin = $shot->annotations()->create([
            'x' => 0.5, 'y' => 0.5, 'severity' => 'must-fix', 'body' => 'Owner mark', 'number' => 1,
        ]);
        $finding = $shot->findings()->create([
            'source' => Finding::SOURCE_AGENT, 'severity' => 'polish', 'body' => 'Agent hint',
        ]);

        Livewire::test('review-page', ['token' => $review->share_token])
            ->call('approve')
            ->call('requestChanges')
            ->call('deletePin', $pin->id)
            ->call('acceptFinding', $finding->id)
            ->call('dismissFinding', $finding->id)
            ->call('regenerateShareToken')
            ->assertOk();

        $this->assertSame('pending', $review->fresh()->status);
        $this->assertSame($review->share_token, $review->fresh()->share_token);
        $this->assertNotNull($pin->fresh());
        $this->assertSame(Finding::STATUS_OPEN, $finding->fresh()->status ?? Finding::STATUS_OPEN);
        $this->assertSame(1, $shot->annotations()->count());
    }

    public function test_owner_accepts_guest_suggestion_as_pin(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Accept guest',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $suggestion = $shot->findings()->create([
            'source' => Finding::SOURCE_GUEST,
            'author' => 'Sam',
            'severity' => 'nit',
            'body' => 'Nudge the logo left.',
            'x' => 0.25,
            'y' => 0.75,
            'status' => Finding::STATUS_OPEN,
        ]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('acceptFinding', $suggestion->id)
            ->assertOk();

        $pin = $shot->annotations()->firstOrFail();
        $this->assertSame('nit', $pin->severity);
        $this->assertSame('Nudge the logo left.', $pin->body);
        $this->assertEqualsWithDelta(0.25, $pin->x, 0.0001);
        $this->assertEqualsWithDelta(0.75, $pin->y, 0.0001);

        $this->assertSame(Finding::STATUS_ACCEPTED, $suggestion->fresh()->status);
        $this->assertSame($pin->number, $suggestion->fresh()->related_pin);
    }

    public function test_payload_surfaces_dispositions_and_guest_counts(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $token = $this->postJson('/api/try-token')->json('token');
        $id = $this->withToken($token)->postJson('/api/reviews', [
            'title' => 'Dispositions',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $review = Review::query()->where('public_id', $id)->firstOrFail();
        $shot = $review->screenshots()->firstOrFail();

        $accepted = $shot->findings()->create([
            'source' => Finding::SOURCE_AGENT, 'severity' => 'a11y', 'body' => 'Label the icon button.',
        ]);
        $dismissed = $shot->findings()->create([
            'source' => Finding::SOURCE_AGENT, 'severity' => 'polish', 'body' => 'Rounder corners maybe.',
        ]);
        $shot->findings()->create([
            'source' => Finding::SOURCE_GUEST,
            'author' => 'Sam',
            'severity' => 'question',
            'body' => 'Secret guest note before triage.',
            'x' => 0.5,
            'y' => 0.5,
        ]);

        Livewire::test('review-page', ['token' => $review->token])
            ->call('acceptFinding', $accepted->id)
            ->call('dismissFinding', $dismissed->id)
            ->assertOk();

        $payload = $review->fresh(['screenshots.annotations', 'screenshots.findings'])->toAgentPayload();

        $this->assertEmpty($payload['work_packets']['second_opinion']);
        $this->assertCount(2, $payload['work_packets']['second_opinion_resolved']);

        $statuses = collect($payload['work_packets']['second_opinion_resolved'])->pluck('status', 'body');
        $this->assertSame(Finding::STATUS_ACCEPTED, $statuses['Label the icon button.']);
        $this->assertSame(Finding::STATUS_DISMISSED, $statuses['Rounder corners maybe.']);

        $this->assertSame(1, $payload['loop']['second_opinion_accepted_count']);
        $this->assertSame(1, $payload['loop']['second_opinion_dismissed_count']);
        $this->assertSame(1, $payload['loop']['guest_suggestion_count']);

        $this->assertStringContainsString($review->share_token, $payload['guest_share_url']);
        $this->assertStringNotContainsString('Secret guest note before triage.', json_encode($payload));
    }

    public function test_reviews_are_scoped_to_try_token(): void
    {
        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.second_opinion_enabled' => false,
        ]);

        $tokenA = $this->postJson('/api/try-token')->json('token');
        $tokenB = $this->postJson('/api/try-token')->json('token');

        $userA = PersonalAccessToken::findToken($tokenA)?->tokenable;
        $userB = PersonalAccessToken::findToken($tokenB)?->tokenable;

        $this->assertInstanceOf(User::class, $userA);
        $this->assertInstanceOf(User::class, $userB);
        $this->assertNotSame($userA->workspace_id, $userB->workspace_id);

        $id = $this->actingAs($userA, 'sanctum')->postJson('/api/reviews', [
            'title' => 'Only A',
            'images' => [$this->tinyPngDataUrl()],
        ])->json('id');

        $this->actingAs($userB, 'sanctum')->getJson('/api/reviews/'.$id)->assertNotFound();
        $this->actingAs($userA, 'sanctum')->getJson('/api/reviews/'.$id)->assertOk();
    }
}
