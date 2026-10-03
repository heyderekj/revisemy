<?php

namespace App\Support;

/**
 * Every public marketing page, once: the sitemap, llms.txt and the "where
 * next" lists all read from here, so a page added or folded away changes in
 * one place.
 */
final class MarketingPages
{
    /**
     * @return list<array{href: string, label: string, line: string, icon: ?string}>
     */
    public static function all(): array
    {
        $pages = [
            ['href' => '/connectors', 'label' => 'Connectors', 'line' => 'Connect Claude, ChatGPT, Cursor, VS Code, Grok, Muse or Codex.', 'icon' => 'puzzle-piece'],
        ];

        foreach (config('guides.pages', []) as $slug => $page) {
            if ($slug !== 'connectors') {
                $pages[] = ['href' => $page['path'], 'label' => $page['label'], 'line' => $page['description'], 'icon' => $page['icon'] ?? null];
            }
        }

        $pages[] = ['href' => '/for', 'label' => 'Built for', 'line' => 'Review types and who uses them.', 'icon' => 'squares-2x2'];

        foreach (config('use-cases.pages', []) + config('use-cases.audiences', []) as $slug => $page) {
            $pages[] = ['href' => '/for/'.$slug, 'label' => $page['label'], 'line' => $page['description'], 'icon' => $page['icon'] ?? null];
        }

        $pages[] = ['href' => '/alternatives', 'label' => 'Alternatives', 'line' => 'When ReviseMy fits, and when something else does.', 'icon' => 'arrows-right-left'];

        foreach (config('alternatives.pages', []) as $slug => $page) {
            $pages[] = ['href' => '/alternatives/'.$slug, 'label' => $page['label'], 'line' => $page['description'], 'icon' => $page['icon'] ?? null];
        }

        return $pages;
    }

    /**
     * A few good next steps, leaving out the page you're on.
     *
     * @return list<array{href: string, label: string, line: string, icon: ?string}>
     */
    public static function more(string $except = ''): array
    {
        $picks = [
            ['href' => '/connectors', 'label' => 'Connectors', 'line' => 'Connect the assistant you already use.', 'icon' => 'puzzle-piece'],
            ['href' => '/second-opinion', 'label' => 'Second opinion', 'line' => 'Hints that never override your marks.', 'icon' => 'sparkles'],
            ['href' => '/board', 'label' => 'The board', 'line' => 'Every mark from open to verified.', 'icon' => 'queue-list'],
            ['href' => '/alternatives', 'label' => 'Alternatives', 'line' => 'When something else fits better.', 'icon' => 'arrows-right-left'],
        ];

        return array_values(array_filter($picks, fn (array $p) => $p['href'] !== $except));
    }
}
