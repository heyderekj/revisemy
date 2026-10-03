<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BumpVersion extends Command
{
    protected $signature = 'revisemy:bump
                            {part : major, minor, or patch}
                            {--title= : Optional release title for the changelog stub}
                            {--date= : Optional release date (YYYY-MM-DD, default today)}';

    protected $description = 'Bump SemVer in config/revisemy.php and prepend a changelog stub';

    public function handle(): int
    {
        $part = strtolower((string) $this->argument('part'));

        if (! in_array($part, ['major', 'minor', 'patch'], true)) {
            $this->error('Part must be major, minor, or patch.');

            return self::FAILURE;
        }

        $current = (string) config('revisemy.version', '0.0.0');

        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $current, $matches)) {
            $this->error("Current version \"{$current}\" is not valid SemVer MAJOR.MINOR.PATCH.");

            return self::FAILURE;
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) $matches[3];

        match ($part) {
            'major' => [$major, $minor, $patch] = [$major + 1, 0, 0],
            'minor' => [$minor, $patch] = [$minor + 1, 0],
            'patch' => $patch = $patch + 1,
        };

        $next = "{$major}.{$minor}.{$patch}";
        $date = $this->option('date') ?: now()->timezone(config('app.timezone'))->toDateString();
        $title = (string) ($this->option('title') ?: '');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Date must be YYYY-MM-DD.');

            return self::FAILURE;
        }

        $versionPath = config_path('revisemy.php');
        $changelogPath = config_path('changelog.php');

        $versionContents = file_get_contents($versionPath);
        $changelogContents = file_get_contents($changelogPath);

        if ($versionContents === false || $changelogContents === false) {
            $this->error('Could not read version or changelog config.');

            return self::FAILURE;
        }

        $updatedVersion = preg_replace(
            "/('version'\\s*=>\\s*)'[^']+'/",
            "\$1'{$next}'",
            $versionContents,
            1,
            $versionCount,
        );

        if ($versionCount !== 1 || $updatedVersion === null) {
            $this->error('Could not update version in config/revisemy.php.');

            return self::FAILURE;
        }

        $titleExport = var_export($title, true);
        $entry = <<<PHP
        [
            'version' => '{$next}',
            'date' => '{$date}',
            'title' => {$titleExport},
            'highlights' => [
                // Fill before committing.
            ],
            'links' => [
            ],
        ],

PHP;

        $updatedChangelog = preg_replace(
            "/('entries'\\s*=>\\s*\\[)\\s*/",
            "\$1\n\n{$entry}\n",
            $changelogContents,
            1,
            $changelogCount,
        );

        if ($changelogCount !== 1 || $updatedChangelog === null) {
            $this->error('Could not prepend changelog stub in config/changelog.php.');

            return self::FAILURE;
        }

        if (file_put_contents($versionPath, $updatedVersion) === false) {
            $this->error('Failed to write config/revisemy.php.');

            return self::FAILURE;
        }

        if (file_put_contents($changelogPath, $updatedChangelog) === false) {
            file_put_contents($versionPath, $versionContents);
            $this->error('Failed to write config/changelog.php; version file restored.');

            return self::FAILURE;
        }

        // The registry listing and the Claude plugin carry the version too.
        foreach (['server.json', 'plugin/.claude-plugin/plugin.json'] as $manifest) {
            $this->bumpManifest(base_path($manifest), $current, $next);
        }

        $this->info("Bumped {$current} → {$next}");
        $this->line('Fill highlights in config/changelog.php, then commit and tag.');

        return self::SUCCESS;
    }

    /** Rewrites a manifest's "version": "x.y.z" line, leaving the rest of the file as written. */
    protected function bumpManifest(string $path, string $current, string $next): void
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return;
        }

        $updated = preg_replace('/"version":\s*"'.preg_quote($current, '/').'"/', '"version": "'.$next.'"', $contents, 1, $count);

        if ($count === 1 && $updated !== null) {
            file_put_contents($path, $updated);
        } else {
            $this->warn('Left '.basename($path).' alone: its version isn’t '.$current.'.');
        }
    }
}
