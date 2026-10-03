<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\Screenshot;
use App\Services\ReviewService;
use App\Services\TryTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneReviewsTest extends TestCase
{
    use RefreshDatabase;

    private function review(): Review
    {
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        return app(ReviewService::class)->create(app(TryTokenService::class)->create()['workspace'], 'Pass', null, [$png]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['filesystems.revisemy_disk' => 'public', 'revisemy.second_opinion_enabled' => false]);
    }

    public function test_a_review_long_past_retention_goes_with_its_files(): void
    {
        $review = $this->review();
        $path = $review->screenshots()->firstOrFail()->path;
        Storage::disk('public')->assertExists($path);

        $review->update(['expires_at' => now()->subDays(Review::PRUNE_GRACE_DAYS + 1)]);
        $this->artisan('model:prune', ['--model' => [Review::class]])->assertSuccessful();

        $this->assertModelMissing($review);
        $this->assertSame(0, Screenshot::count());
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_review_inside_its_grace_period_stays(): void
    {
        $review = $this->review();
        $review->update(['expires_at' => now()->subDays(3)]);

        $this->artisan('model:prune', ['--model' => [Review::class]])->assertSuccessful();

        $this->assertModelExists($review);
    }

    public function test_a_kept_later_pass_keeps_its_parent(): void
    {
        $parent = $this->review();
        $child = $this->review();
        $child->update(['parent_id' => $parent->id]);
        $parent->update(['expires_at' => now()->subDays(Review::PRUNE_GRACE_DAYS + 1)]);

        $this->artisan('model:prune', ['--model' => [Review::class]])->assertSuccessful();

        $this->assertModelExists($parent);
    }

    public function test_the_check_names_what_is_missing_and_fails(): void
    {
        config(['app.url' => 'http://localhost', 'nightwatch.enabled' => false]);

        $this->artisan('revisemy:check')
            ->expectsOutputToContain('APP_URL is http://localhost')
            ->expectsOutputToContain('Nightwatch is off')
            ->assertFailed();
    }
}
