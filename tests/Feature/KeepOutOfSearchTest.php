<?php

namespace Tests\Feature;

use Tests\TestCase;

class KeepOutOfSearchTest extends TestCase
{
    public function test_secret_review_links_tell_crawlers_to_keep_out(): void
    {
        $this->get('/r/not-a-real-token')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->get('/reviews')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_the_marketing_site_stays_indexable(): void
    {
        $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    }
}
