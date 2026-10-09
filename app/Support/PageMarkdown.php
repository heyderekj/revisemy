<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Every public page as plain markdown, built from the same config the HTML
 * pages draw. Agents and answer engines read these (/board.md, llms-full.txt)
 * far more easily than the rendered page.
 */
final class PageMarkdown
{
    /**
     * Markdown for a site path, or null when there's no such page.
     */
    public static function for(string $path): ?string
    {
        $path = '/'.trim($path, '/');

        return match (true) {
            $path === '/' || $path === '/index' => self::home(),
            $path === '/for' => self::useCaseIndex(),
            $path === '/alternatives' => self::alternativeIndex(),
            $path === '/docs' => self::doc('index'),
            $path === '/docs/index' => null,
            str_starts_with($path, '/docs/') => self::doc(substr($path, 6)),
            str_starts_with($path, '/for/') => self::useCase(substr($path, 5)),
            str_starts_with($path, '/alternatives/') => self::alternative(substr($path, 14)),
            default => self::guide($path),
        };
    }

    /**
     * The config entry behind a guide, use-case or alternative page.
     *
     * @return array<string, mixed>|null
     */
    public static function page(string $path): ?array
    {
        $path = '/'.trim($path, '/');

        return match (true) {
            str_starts_with($path, '/for/') => config('use-cases.pages.'.substr($path, 5)) ?? config('use-cases.audiences.'.substr($path, 5)),
            str_starts_with($path, '/alternatives/') => config('alternatives.pages.'.substr($path, 14)),
            default => collect(config('guides.pages', []))->firstWhere('path', $path),
        };
    }

    /**
     * Every path that has a markdown twin, homepage first.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return ['/', ...array_column(MarketingPages::all(), 'href')];
    }

    public static function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').($path === '/' ? '/index.md' : $path.'.md');
    }

    protected static function home(): string
    {
        $md = self::head(config('seo.name').' — '.config('seo.tagline'), config('seo.description'), '/');

        $md .= "## How it works\n\n"
            ."1. Your agent captures the work — screenshots, a live URL (desktop and phone), a PDF deck or email HTML — and calls `create_review`.\n"
            ."2. You open the review link and mark what matters: must fix, nice to have, question or keep. A second opinion adds hints that stay suggestions.\n"
            ."3. You approve or ask for changes. Your agent reads your marks as work through `get_review`, fixes them, and opens the next pass.\n\n";

        $md .= "## Connect\n\n";
        foreach (Hosts::all() as $host) {
            $md .= '- **'.$host['name'].'** — '.Hosts::modeLabel($host['mode']).($host['where'] ? ' · '.$host['where'] : '')."\n";
        }
        $md .= "\nMCP endpoint: ".McpCatalog::endpoint()."\n\n";

        $md .= self::faq(HomeFaq::plain());

        return $md;
    }

    protected static function guide(string $path): ?string
    {
        $page = collect(config('guides.pages', []))->firstWhere('path', $path);

        if (! $page) {
            return null;
        }

        $md = self::head($page['headline'], $page['subheadline'] ?? null, $path);
        $md .= self::story($page);

        if (! empty($page['hosts'])) {
            $md .= "## Assistants\n\n";
            foreach (Hosts::all() as $host) {
                $md .= '### '.$host['name'].' ('.Hosts::modeLabel($host['mode']).")\n\n";
                foreach ($host['steps'] ?? [] as $i => $step) {
                    $md .= ($i + 1).'. '.self::text($step)."\n";
                }
                $md .= "\n";
            }
        }

        foreach ($page['sections'] ?? [] as $section) {
            $md .= '## '.$section['heading']."\n\n".self::text($section['body'])."\n\n".self::bullets($section['items'] ?? []);
        }

        if (! empty($page['changelog'])) {
            foreach (config('changelog.entries', []) as $entry) {
                $md .= '## '.$entry['version'].' — '.$entry['title'].' ('.$entry['date'].")\n\n";
                $md .= self::bullets($entry['highlights'] ?? []);
            }
        }

        return $md.self::faq($page['faq'] ?? []);
    }

    /** A developer doc is markdown already; it only needs the page's head. */
    protected static function doc(string $slug): ?string
    {
        $page = DeveloperDocs::find($slug);

        if ($page === null) {
            return null;
        }

        return self::head($page['title'], $page['description'], $page['path']).DeveloperDocs::markdown($page['slug']);
    }

    protected static function useCase(string $slug): ?string
    {
        $page = config("use-cases.pages.{$slug}") ?? config("use-cases.audiences.{$slug}");

        if (! $page) {
            return null;
        }

        $md = self::head($page['headline'], $page['subheadline'] ?? null, '/for/'.$slug);
        $md .= self::story($page);

        if (! empty($page['inputs']['items'])) {
            $md .= "## Getting it in\n\n".self::text($page['inputs']['intro'] ?? '')."\n\n";
            foreach ($page['inputs']['items'] as $input) {
                $md .= '- **'.$input['label'].'** (`'.$input['key'].'`) — '.self::text($input['body'])."\n";
            }
            $md .= "\n";
        }

        if (! empty($page['prompts'])) {
            $md .= "## Ask your agent\n\n".self::bullets(array_map(fn ($p) => '“'.$p.'”', $page['prompts']));
        }

        return $md.self::faq($page['faq'] ?? []);
    }

    protected static function alternative(string $slug): ?string
    {
        $page = config("alternatives.pages.{$slug}");

        if (! $page) {
            return null;
        }

        $competitor = $page['competitor'];
        $md = self::head($page['headline'], $page['subheadline'] ?? null, '/alternatives/'.$slug);

        if (! empty($page['why_look'])) {
            $md .= "## Why people look for a {$competitor} alternative\n\n".self::bullets($page['why_look']);
        }
        if (! empty($page['what_to_look_for'])) {
            $md .= "## What to look for\n\n".self::bullets($page['what_to_look_for']);
        }
        if (! empty($page['recommended'])) {
            $md .= "## Worth a look\n\n";
            foreach ($page['recommended'] as $pick) {
                $md .= '### '.$pick['label']."\n\n".self::text($pick['summary'] ?? '')."\n\n";
                if (filled($pick['best_for'] ?? null)) {
                    $md .= 'Best for: '.self::text($pick['best_for'])."\n\n";
                }
                $md .= self::bullets($pick['bullets'] ?? []);
            }
        }
        if (! empty($page['keep_theirs'])) {
            $md .= "## When to keep {$competitor}\n\n".self::bullets($page['keep_theirs']);
        }

        return $md.self::faq($page['faq'] ?? []);
    }

    protected static function useCaseIndex(): string
    {
        $md = self::head('Anything visual, anyone in the loop', 'Pick what you’re reviewing, or who you are.', '/for');

        $md .= "## Review types\n\n";
        foreach (config('use-cases.pages', []) as $slug => $page) {
            $md .= '- ['.$page['label'].']('.self::url('/for/'.$slug).') — '.$page['headline']."\n";
        }
        $md .= "\n## Who it’s for\n\n";
        foreach (config('use-cases.audiences', []) as $slug => $page) {
            $md .= '- ['.$page['label'].']('.self::url('/for/'.$slug).') — '.$page['headline']."\n";
        }

        return $md."\n";
    }

    protected static function alternativeIndex(): string
    {
        $md = self::head('Fair comparisons, not dunk contests', 'When ReviseMy’s loop fits — and when Figma comments, a website annotation tool or just an AI chat app is the better choice.', '/alternatives');

        foreach (config('alternatives.pages', []) as $slug => $page) {
            $md .= '- ['.$page['label'].']('.self::url('/alternatives/'.$slug).') — '.$page['teaser']."\n";
        }

        return $md."\n";
    }

    /** The problem, the loop and its steps, features and checklist, as a page has them. */
    protected static function story(array $page): string
    {
        $md = '';

        if (filled($page['problem'] ?? null)) {
            $md .= "## The problem\n\n".self::text($page['problem'])."\n\n";
        }
        if (filled($page['loop'] ?? null)) {
            $md .= "## How ReviseMy fits\n\n".self::text($page['loop'])."\n\n";
            foreach ($page['loop_steps'] ?? [] as $i => $step) {
                $md .= ($i + 1).'. '.self::step($step)."\n";
            }
            $md .= empty($page['loop_steps']) ? '' : "\n";
        }
        if (! empty($page['features'])) {
            $md .= '## '.($page['features_heading'] ?? 'What you get')."\n\n";
            foreach ($page['features'] as $feature) {
                $md .= '- **'.$feature['title'].'** — '.self::text($feature['body'])."\n";
            }
            $md .= "\n";
        }
        if (! empty($page['checklist'])) {
            $md .= '## '.($page['checklist_heading'] ?? 'Checklist')."\n\n";
            if (filled($page['checklist_intro'] ?? null)) {
                $md .= self::text($page['checklist_intro'])."\n\n";
            }
            $md .= self::bullets($page['checklist']);
        }

        return $md;
    }

    /** A loop step: plain text, optionally led by a command and followed by mixed runs. */
    protected static function step(array|string $step): string
    {
        if (is_string($step)) {
            return self::text($step);
        }

        $line = filled($step['command'] ?? null) ? '`'.$step['command'].'` ' : '';
        $line .= $step['text'] ?? '';

        foreach ($step['after'] ?? [] as $run) {
            $line .= ($run['type'] ?? 'text') === 'command' ? '`'.$run['value'].'`' : $run['value'];
        }

        return self::text($line);
    }

    protected static function head(string $title, ?string $lead, string $path): string
    {
        $md = '# '.self::text($title)."\n\n";

        if (filled($lead)) {
            $md .= '> '.self::text($lead)."\n\n";
        }

        $page = rtrim((string) config('app.url'), '/').($path === '/' ? '/' : $path);

        return $md.'Page: '.$page."\n\n";
    }

    /**
     * @param  list<array{q: string, a: string}>  $faq
     */
    protected static function faq(array $faq): string
    {
        if ($faq === []) {
            return '';
        }

        $md = "## Questions\n\n";
        foreach ($faq as $item) {
            $md .= '### '.self::text($item['q'])."\n\n".self::text($item['a'])."\n\n";
        }

        return $md;
    }

    /**
     * @param  list<string>  $items
     */
    protected static function bullets(array $items): string
    {
        return $items === [] ? '' : collect($items)->map(fn ($item) => '- '.self::text((string) $item))->implode("\n")."\n\n";
    }

    /** Config copy may carry a little HTML; markdown wants plain text. */
    protected static function text(string $value): string
    {
        return Str::squish(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5));
    }
}
