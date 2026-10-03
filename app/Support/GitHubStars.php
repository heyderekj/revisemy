<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The repo's GitHub star count, for the homepage's Open source section.
 * Cached for an hour and refreshed after the response once stale, so a page
 * view never waits on GitHub. Null when GitHub can't be reached; the page
 * then just leaves the count off.
 */
final class GitHubStars
{
    public const REPO = 'heyderekj/revisemy';

    public static function count(): ?int
    {
        return Cache::flexible('github-stars:'.self::REPO, [3600, 86400], fn () => self::fetch());
    }

    protected static function fetch(): ?int
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent('ReviseMy')
                ->timeout(3)
                ->get('https://api.github.com/repos/'.self::REPO);

            $stars = $response->successful() ? $response->json('stargazers_count') : null;

            return is_int($stars) ? $stars : null;
        } catch (Throwable) {
            return null;
        }
    }
}
