<?php

namespace App\Support;

use App\Models\Review;
use App\Services\SecondOpinionService;

/**
 * What the privacy and security page says, read from how this install is set
 * up rather than written once and left to drift. If vision is switched on,
 * the page stops saying screenshots stay with ReviseMy and names who sees
 * them; the same for capture, error tracking, analytics and payments.
 */
class TrustFacts
{
    /** Days a review opens on each plan, then the grace before it's deleted. */
    public static function retention(): array
    {
        return [
            'try' => (int) config('billing.plans.free.review_retention_days', 7),
            'plus' => (int) config('billing.plans.pro.review_retention_days', 90),
            'grace' => Review::PRUNE_GRACE_DAYS,
        ];
    }

    /** Who sees screenshots for vision hints, or null when nobody does. */
    public static function vision(): ?string
    {
        return config('revisemy.second_opinion_enabled', true)
            ? app(SecondOpinionService::class)->visionProviderName()
            : null;
    }

    /** Who renders live pages and emails, or null when capture is off. */
    public static function capture(): ?string
    {
        return match (config('revisemy.capture.driver')) {
            'hosted' => str_contains(strtolower((string) parse_url((string) config('revisemy.capture.endpoint'), PHP_URL_HOST)), 'browserless')
                ? 'Browserless'
                : (parse_url((string) config('revisemy.capture.endpoint'), PHP_URL_HOST) ?: 'a hosted browser service'),
            'browsershot' => 'a browser on ReviseMy’s own server',
            default => null,
        };
    }

    public static function errorTracking(): bool
    {
        return (bool) config('nightwatch.enabled') && filled(config('nightwatch.token'));
    }

    public static function analytics(): bool
    {
        return filled(config('seo.fathom_site_id'));
    }

    public static function payments(): bool
    {
        return (bool) config('billing.pricing_enabled');
    }

    /**
     * Everything /security says, for the page and its markdown twin, so the
     * two can't drift.
     *
     * @return array{headline: string, lead: string, who: list<array{icon: string, title: string, body: string}>, where: list<string>, kept: list<string>, open_source: string, report: string, faq: list<array{q: string, a: string}>}
     */
    public static function page(): array
    {
        $retention = self::retention();
        $vision = self::vision();
        $capture = self::capture();
        $hosting = (string) config('revisemy.security.hosting');

        return [
            'headline' => 'Unreleased work, kept to the people you send it to.',
            'lead' => 'Reviews hold things you haven’t shipped. Here’s who can open them, where they go, and when they’re gone.',
            'who' => [
                ['icon' => 'key', 'title' => 'A link only you hold', 'body' => 'Each review link carries 40 random characters. There’s no list of reviews to browse, and review pages ask search engines not to index them. Share it like you’d share a private document.'],
                ['icon' => 'users', 'title' => 'Guests suggest, you decide', 'body' => 'A guest link is a separate key. Guests leave suggestions, never marks or decisions. Set it to expire, or make a new one and the old link stops working.'],
                ['icon' => 'photo', 'title' => 'Screenshots stay behind the link', 'body' => 'Images are served through the review’s own link, never from a public file address.'],
                ['icon' => 'puzzle-piece', 'title' => 'Your assistant, on your terms', 'body' => 'An assistant that connects gets a token that lasts an hour and renews quietly. Disconnect it any time on Your reviews. Connect shows where a sign-in really sends you, and warns about apps that only look like Claude.'],
            ],
            'where' => array_values(array_filter([
                "ReviseMy runs on {$hosting}. Reviews live in its database and screenshots in its object storage. Every connection is HTTPS.",
                $capture
                    ? "Live pages and emails are rendered by {$capture}: it opens the page you asked for and sends back the image."
                    : 'Capture is off, so ReviseMy only sees the screenshots your assistant sends.',
                $vision
                    ? "Vision hints send each screenshot to {$vision} through its API, to suggest what to look at."
                    : 'The second opinion here is the built-in checklist. No screenshot is sent to an AI model.',
                'Your assistant sees what it sends and what your review says back, in its own chat and under its own terms. ReviseMy never sees that chat.',
                self::analytics() ? 'Fathom counts visits to these pages. It never loads on review or billing links.' : null,
                self::errorTracking() ? 'Laravel Nightwatch gets errors and a sample of requests, without what was in them.' : null,
                self::payments() ? 'Polar handles payment. Card numbers never reach ReviseMy.' : null,
                'Nothing you put in a review is sold, and none of it trains a model.',
            ])),
            'kept' => [
                "A review opens for {$retention['try']} days on Try and {$retention['plus']} on Plus. {$retention['grace']} days after that, it’s deleted with its screenshots.",
                'Delete it sooner from the review: Share, then Delete review. Every pass goes at once, and the links stop working.',
                'Disconnecting an assistant ends its access straight away. Your reviews stay.',
                'Connect’s logs record which assistant, where it returns to and what happened. Never a sign-in code, token or key.',
            ],
            'open_source' => 'ReviseMy is open source. Everything on this page is in the code on GitHub, and you can run it yourself to keep every review on your own servers.',
            'report' => 'Report a security problem privately on GitHub rather than in a public issue, so it can be fixed before anyone else hears about it.',
            'faq' => [
                ['q' => 'Can someone guess a review link?', 'a' => 'Not in practice: 40 random letters and digits is far too many to guess. Anyone you send it to can open it, so send it only to people you mean to.'],
                ['q' => 'Does an AI model see my screenshots?', 'a' => $vision
                    ? "Yes, for vision hints: each screenshot goes to {$vision} through its API. Your marks and decisions don’t."
                    : 'Not on this site. The second opinion here is the built-in checklist, which runs on ReviseMy’s own server.'],
                ['q' => 'Can I delete everything now?', 'a' => 'Delete each review from its page. Then revoke the try token and disconnect your assistants on Your reviews, and nothing of yours can open the workspace.'],
                ['q' => 'Where’s the legal version?', 'a' => 'The privacy policy and terms cover the same ground in fuller words.'],
            ],
        ];
    }

    /** Where to report a security problem privately. */
    public static function reportUrl(): string
    {
        return (string) config('revisemy.security.report_url', 'https://github.com/heyderekj/revisemy/security/advisories/new');
    }
}
