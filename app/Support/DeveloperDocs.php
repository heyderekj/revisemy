<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;

/**
 * The developer docs at /docs, written as markdown files in docs/developers
 * so they read on GitHub, take a pull request like any other file, and serve
 * as /docs/*.md for agents unchanged.
 *
 * Anything that would drift is generated rather than written. A file wraps
 * a line for GitHub readers in <!-- generated:tools --> … <!-- /generated -->
 * and the site swaps the span for the real thing, read from McpCatalog (the
 * same source llms.txt and the server card use) or the billing config.
 */
final class DeveloperDocs
{
    public const DIRECTORY = 'docs/developers';

    /** The address the examples are written against. */
    protected const HOSTED = 'https://revisemy.com';

    /** @var list<array{slug: string, path: string, title: string, nav: string, description: string, order: int, icon: string, file: string}>|null */
    protected static ?array $pages = null;

    /**
     * Every doc, index first, in reading order.
     *
     * @return list<array{slug: string, path: string, title: string, nav: string, description: string, order: int, icon: string, file: string}>
     */
    public static function pages(): array
    {
        return self::$pages ??= collect(glob(base_path(self::DIRECTORY.'/*.md')) ?: [])
            ->map(function (string $file) {
                $slug = basename($file, '.md');
                $meta = self::frontMatter((string) file_get_contents($file))[0];

                return [
                    'slug' => $slug,
                    'path' => $slug === 'index' ? '/docs' : '/docs/'.$slug,
                    'title' => $meta['title'] ?? $slug,
                    'nav' => $meta['nav'] ?? $meta['title'] ?? $slug,
                    'description' => $meta['description'] ?? '',
                    'order' => (int) ($meta['order'] ?? 99),
                    'icon' => $meta['icon'] ?? 'document',
                    'file' => self::DIRECTORY.'/'.$slug.'.md',
                ];
            })
            ->sortBy('order')
            ->values()
            ->all();
    }

    /**
     * @return array{slug: string, path: string, title: string, nav: string, description: string, order: int, icon: string, file: string}|null
     */
    public static function find(string $slug): ?array
    {
        return collect(self::pages())->firstWhere('slug', $slug);
    }

    /**
     * The page body as markdown, generated parts filled in.
     */
    public static function markdown(string $slug): ?string
    {
        $page = self::find($slug);

        if ($page === null) {
            return null;
        }

        $body = self::frontMatter((string) file_get_contents(base_path($page['file'])))[1];

        $body = preg_replace_callback(
            '/<!-- generated:([a-z-]+) -->.*?<!-- \/generated -->/s',
            fn (array $match) => match ($match[1]) {
                'tools' => self::toolReference(),
                'next-actions' => self::nextActionTable(),
                'costs' => self::costTable(),
                default => $match[0],
            },
            $body,
        );

        // Examples name the hosted app; a self-hosted copy names itself.
        return trim(str_replace(self::HOSTED, rtrim((string) config('app.url'), '/'), $body))."\n";
    }

    /**
     * The page as HTML, with the h2 headings for an "On this page" list.
     *
     * @return array{html: string, headings: list<array{id: string, text: string}>}|null
     */
    public static function render(string $slug): ?array
    {
        $markdown = self::markdown($slug);

        if ($markdown === null) {
            return null;
        }

        $rendered = self::converter()->convert($markdown);
        $headings = [];

        foreach ($rendered->getDocument()->iterator() as $node) {
            if (! $node instanceof Heading || $node->getLevel() !== 2) {
                continue;
            }

            $permalink = collect($node->children())->first(fn ($child) => $child instanceof HeadingPermalink);
            $text = collect($node->children())
                ->filter(fn ($child) => ! $child instanceof HeadingPermalink)
                ->map(fn ($child) => self::plainText($child))
                ->implode('');

            if ($permalink) {
                $headings[] = ['id' => $permalink->getSlug(), 'text' => trim($text)];
            }
        }

        return ['html' => $rendered->getContent(), 'headings' => $headings];
    }

    /** The doc before and after this one, for the links at the foot of a page. */
    public static function neighbours(string $slug): array
    {
        $pages = self::pages();
        $index = array_search($slug, array_column($pages, 'slug'), true);

        return [
            'previous' => $index > 0 ? $pages[$index - 1] : null,
            'next' => $index !== false ? ($pages[$index + 1] ?? null) : null,
        ];
    }

    /** Where to edit a doc on GitHub. */
    public static function editUrl(string $slug): ?string
    {
        $page = self::find($slug);

        return $page ? rtrim((string) config('seo.github'), '/').'/blob/main/'.$page['file'] : null;
    }

    /** Forget the parsed list, for tests that change the files or the config. */
    public static function flush(): void
    {
        self::$pages = null;
    }

    protected static function toolReference(): string
    {
        $md = '';

        foreach (McpCatalog::reference() as $tool) {
            $md .= "### `{$tool['name']}`\n\n{$tool['description']}\n\n";

            if ($tool['parameters'] === []) {
                $md .= "No parameters.\n\n";

                continue;
            }

            $md .= "| Parameter | Type | Required | What it is |\n|---|---|---|---|\n";

            foreach ($tool['parameters'] as $parameter) {
                $md .= '| `'.$parameter['name'].'` | '.self::cell($parameter['type']).' | '.($parameter['required'] ? 'Yes' : 'No').' | '.self::cell($parameter['description'])." |\n";
            }

            $md .= "\n";
        }

        return rtrim($md);
    }

    protected static function nextActionTable(): string
    {
        $md = "| `next_action` | What your agent does |\n|---|---|\n";

        foreach (McpCatalog::nextActions() as $action => $meaning) {
            $md .= '| `'.$action.'` | '.self::cell($meaning)." |\n";
        }

        return rtrim($md);
    }

    protected static function costTable(): string
    {
        $labels = [
            'images' => '`images` (screenshots you send)',
            'pdf' => '`pdf` (one shot per page)',
            'html' => '`html` (an email, rendered)',
            'capture_url' => '`capture_url` (desktop, mobile and tablet capture of `page_url`)',
        ];

        $md = "| Source | Credits |\n|---|---|\n";

        foreach (config('billing.costs', []) as $source => $credits) {
            $md .= '| '.($labels[$source] ?? '`'.$source.'`').' | '.$credits." |\n";
        }

        return $md."\nA try workspace gets ".(int) config('billing.plans.free.credits').' credits a month. Only `create_review` spends them: reading a review, resolving marks and webhooks cost nothing.';
    }

    /** A value that has to sit inside one table cell. */
    protected static function cell(string $value): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $value);
    }

    /**
     * Split flat `key: value` front matter from the body. The docs only need
     * a title, a short nav label, a description, an order and an icon, so there's no
     * YAML parser behind this.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    protected static function frontMatter(string $contents): array
    {
        if (! preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', $contents, $match)) {
            return [[], $contents];
        }

        $meta = [];

        foreach (preg_split('/\R/', $match[1]) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', $line, $pair)) {
                $meta[$pair[1]] = trim($pair[2], " \t\"'");
            }
        }

        return [$meta, $match[2]];
    }

    protected static function converter(): MarkdownConverter
    {
        $environment = new Environment([
            'heading_permalink' => [
                'html_class' => 'rm-docs-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'symbol' => '#',
                'aria_hidden' => true,
                'title' => 'Link to this section',
            ],
            'external_link' => [
                'internal_hosts' => [parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'revisemy.com'],
                'open_in_new_window' => true,
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addExtension(new ExternalLinkExtension);

        return new MarkdownConverter($environment);
    }

    protected static function plainText(object $node): string
    {
        if ($node instanceof Text || method_exists($node, 'getLiteral')) {
            return $node->getLiteral();
        }

        return collect($node->children())->map(fn ($child) => self::plainText($child))->implode('');
    }
}
