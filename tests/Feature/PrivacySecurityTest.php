<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Screenshot;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The privacy and security page says what this install actually does, and
 * an owner can delete a review instead of waiting for it to lapse.
 */
class PrivacySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config([
            'filesystems.revisemy_disk' => 'public',
            'revisemy.anthropic.api_key' => null,
            'revisemy.openai.api_key' => null,
            'revisemy.openai.base_url' => null,
        ]);
    }

    private function review(?Review $parent = null): Review
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $workspace = $parent?->workspace ?? app(TryTokenService::class)->create()['workspace'];

        return app(ReviewService::class)->create($workspace, 'Pass', null, [$png], parentPublicId: $parent?->public_id);
    }

    public function test_the_page_says_screenshots_stay_here_while_vision_is_off(): void
    {
        $this->get('/security')
            ->assertOk()
            ->assertSee('Unreleased work, kept to the people you send it to.')
            ->assertSee('No screenshot is sent to an AI model.')
            ->assertSee('A review opens for 7 days on Try and 90 on Plus. 30 days after that', false)
            ->assertSee('Delete review')
            ->assertSee('https://github.com/heyderekj/revisemy/security/advisories/new');
    }

    public function test_the_page_names_who_sees_screenshots_when_vision_is_on(): void
    {
        config(['revisemy.anthropic.api_key' => 'sk-ant-test']);

        $this->get('/security')
            ->assertOk()
            ->assertSee('Vision hints send each screenshot to Anthropic through its API')
            ->assertDontSee('No screenshot is sent to an AI model.');
    }

    public function test_the_page_names_the_capture_service(): void
    {
        config(['revisemy.capture.driver' => 'hosted', 'revisemy.capture.endpoint' => 'https://production-sfo.browserless.io/screenshot']);

        $this->get('/security')->assertSee('rendered by Browserless');

        config(['revisemy.capture.driver' => null]);

        $this->get('/security')->assertSee('Capture is off, so ReviseMy only sees the screenshots your assistant sends.');
    }

    public function test_security_txt_points_at_the_private_report_and_the_page(): void
    {
        $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('Contact: https://github.com/heyderekj/revisemy/security/advisories/new', false)
            ->assertSee('Policy: '.url('/security'), false)
            ->assertSee('Expires: '.now()->addYear()->format('Y-m-d'), false);
    }

    public function test_the_homepage_and_site_link_to_it(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Private by default')
            ->assertSee('Screenshots stay with ReviseMy')
            ->assertSee('href="/security"', false);

        $this->get('/sitemap.xml')->assertSee(url('/security'), false);
    }

    public function test_an_owner_deletes_a_review_with_every_pass_and_its_files(): void
    {
        $first = $this->review();
        $second = $this->review($first);
        $paths = Screenshot::query()->pluck('path');
        $paths->each(fn ($path) => Storage::disk('public')->assertExists($path));

        Livewire::test('review-page', ['token' => $second->token])
            ->call('deleteReview')
            ->assertRedirect('/reviews');

        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertSame(0, Screenshot::count());
        $paths->each(fn ($path) => Storage::disk('public')->assertMissing($path));
        $this->assertSame('Review deleted, with all 2 passes and their screenshots.', session('status'));

        $this->get('/r/'.$first->token)->assertNotFound();
    }

    public function test_a_guest_cannot_delete(): void
    {
        $review = $this->review();

        Livewire::test('review-page', ['token' => $review->share_token])->call('deleteReview');

        $this->assertModelExists($review);
    }
}
