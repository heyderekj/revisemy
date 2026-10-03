<?php

namespace Tests\Feature;

use App\Models\Annotation;
use Tests\TestCase;

class ReviewMockTest extends TestCase
{
    public function test_every_sample_uses_known_severities_and_statuses(): void
    {
        foreach (config('review-samples') as $key => $sample) {
            $this->assertFileExists(resource_path("views/components/review-mock/samples/{$key}.blade.php"));

            foreach ($sample['marks'] as $mark) {
                $this->assertArrayHasKey($mark['severity'], Annotation::severityLabels(), "{$key}: unknown severity");
                $this->assertArrayHasKey($mark['status'], Annotation::statusLabels(), "{$key}: unknown status");
            }
        }
    }

    public function test_home_hero_draws_the_review_page(): void
    {
        $sample = config('review-samples.website');

        $this->get('/')
            ->assertOk()
            ->assertSee($sample['label'], false)
            ->assertSee('My marks')
            ->assertSee('Hints')
            ->assertSee('What to look at')
            ->assertSee(Annotation::severityLabels()[$sample['marks'][0]['severity']])
            ->assertSee($sample['marks'][0]['note']);
    }

    public function test_each_use_case_page_draws_its_sample(): void
    {
        $pages = config('use-cases.pages') + config('use-cases.audiences');

        foreach ($pages as $slug => $page) {
            $sample = config('review-samples.'.($page['sample'] ?? $page['review_type']));
            $this->assertNotNull($sample, "/for/{$slug} has no sample");

            $this->get("/for/{$slug}")
                ->assertOk()
                ->assertSee($sample['label'], false)
                ->assertSee($sample['marks'][0]['note'], false);
        }
    }
}
