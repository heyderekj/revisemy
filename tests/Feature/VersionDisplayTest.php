<?php

namespace Tests\Feature;

use Tests\TestCase;

class VersionDisplayTest extends TestCase
{
    public function test_homepage_shows_configured_version(): void
    {
        $version = config('revisemy.version');

        $this->get('/')
            ->assertOk()
            ->assertSee('v'.$version, false);
    }

    public function test_seo_json_ld_includes_software_version(): void
    {
        $version = config('revisemy.version');

        $this->get('/')
            ->assertOk()
            ->assertSee('"softwareVersion":"'.$version.'"', false);
    }
}
