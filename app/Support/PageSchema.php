<?php

namespace App\Support;

/**
 * The structured data a page earns from its path: the questions it answers
 * (FAQPage) and where it sits in the site (BreadcrumbList).
 */
final class PageSchema
{
    /**
     * @return list<array{q: string, a: string}>
     */
    public static function faq(string $path): array
    {
        $path = '/'.trim($path, '/');

        if ($path === '/') {
            return HomeFaq::plain();
        }

        return collect(PageMarkdown::page($path)['faq'] ?? [])
            ->map(fn (array $item) => ['q' => trim(strip_tags($item['q'])), 'a' => trim(strip_tags($item['a']))])
            ->values()
            ->all();
    }

    /**
     * Home, then each parent section, then the page. Empty for top-level pages.
     *
     * @return list<array{name: string, url: string}>
     */
    public static function breadcrumbs(string $path): array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) < 2) {
            return [];
        }

        $site = rtrim((string) config('app.url'), '/');
        $labels = collect(MarketingPages::all())->pluck('label', 'href');
        $trail = [['name' => config('seo.name'), 'url' => $site.'/']];
        $href = '';

        foreach ($segments as $segment) {
            $href .= '/'.$segment;
            $trail[] = ['name' => $labels[$href] ?? ucfirst(str_replace('-', ' ', $segment)), 'url' => $site.$href];
        }

        return $trail;
    }
}
