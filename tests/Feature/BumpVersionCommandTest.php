<?php

namespace Tests\Feature;

use Tests\TestCase;

class BumpVersionCommandTest extends TestCase
{
    public function test_patch_bump_updates_version_and_changelog_stub(): void
    {
        $versionPath = config_path('revisemy.php');
        $changelogPath = config_path('changelog.php');
        $versionBefore = file_get_contents($versionPath);
        $changelogBefore = file_get_contents($changelogPath);
        $manifests = collect(['server.json', 'plugin/.claude-plugin/plugin.json'])
            ->mapWithKeys(fn (string $path) => [base_path($path) => file_get_contents(base_path($path))]);

        $this->assertNotFalse($versionBefore);
        $this->assertNotFalse($changelogBefore);

        preg_match("/'version'\\s*=>\\s*'(\\d+)\\.(\\d+)\\.(\\d+)'/", $versionBefore, $matches);
        $this->assertNotEmpty($matches, 'Could not parse current SemVer from config/revisemy.php');
        $expected = $matches[1].'.'.$matches[2].'.'.((int) $matches[3] + 1);

        try {
            $this->artisan('revisemy:bump', [
                'part' => 'patch',
                '--title' => 'Test patch',
                '--date' => '2026-07-14',
            ])->assertSuccessful();

            $versionAfter = file_get_contents($versionPath);
            $changelogAfter = file_get_contents($changelogPath);

            $this->assertStringContainsString("'version' => '{$expected}'", $versionAfter);
            $this->assertStringContainsString("'version' => '{$expected}'", $changelogAfter);
            $this->assertStringContainsString("'title' => 'Test patch'", $changelogAfter);
            $this->assertStringContainsString("'date' => '2026-07-14'", $changelogAfter);

            foreach ($manifests->keys() as $path) {
                $this->assertSame($expected, json_decode(file_get_contents($path), true)['version'], basename($path).' keeps step');
            }
        } finally {
            file_put_contents($versionPath, $versionBefore);
            file_put_contents($changelogPath, $changelogBefore);
            $manifests->each(fn (string $contents, string $path) => file_put_contents($path, $contents));
        }
    }

    public function test_invalid_part_fails(): void
    {
        $this->artisan('revisemy:bump', ['part' => 'banana'])
            ->assertFailed();
    }
}
