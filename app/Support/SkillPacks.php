<?php

namespace App\Support;

/**
 * The skill packs from config/skills.php: the ones with a page of their own,
 * and every pack grouped under its category for /skills.
 */
final class SkillPacks
{
    /**
     * Packs that have a page at /skills/{slug}.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function pages(): array
    {
        return array_filter(config('skills.packs', []), fn (array $pack) => isset($pack['path']));
    }

    /**
     * Each category with its packs as links, in config order. Categories
     * marked tab are review types, shown as tabs on /skills.
     *
     * @return list<array{key: string, tab: bool, label: string, line: string, soon: ?string, links: list<array{href: string, label: string, line: string, icon: ?string}>}>
     */
    public static function categories(): array
    {
        $packs = collect(config('skills.packs', []));

        return collect(config('skills.categories', []))
            ->map(fn (array $category, string $key) => [
                'key' => $key,
                'tab' => $category['tab'] ?? false,
                'label' => $category['label'],
                'line' => $category['line'],
                'soon' => $category['soon'] ?? null,
                'links' => $packs->where('category', $key)
                    ->map(fn (array $pack) => [
                        'href' => $pack['path'] ?? $pack['href'],
                        'label' => $pack['label'],
                        'line' => $pack['line'],
                        'icon' => $pack['icon'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $category) => $category['links'] !== [] || $category['soon'] !== null)
            ->values()
            ->all();
    }
}
