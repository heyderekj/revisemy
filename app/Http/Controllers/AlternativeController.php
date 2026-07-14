<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AlternativeController extends Controller
{
    public function index(): View
    {
        $pages = collect(config('alternatives.pages', []))
            ->map(fn (array $entry): array => [
                'slug' => $entry['slug'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'teaser' => $entry['teaser'] ?? $entry['subheadline'],
                'headline' => $entry['headline'],
                'description' => $entry['description'],
            ])
            ->values()
            ->all();

        return view('alternatives.index', [
            'pages' => $pages,
        ]);
    }

    public function show(string $slug): View
    {
        $page = config("alternatives.pages.{$slug}");

        abort_unless(is_array($page), 404);

        $related = collect($page['related'] ?? [])
            ->map(fn (string $relatedSlug): ?array => config("alternatives.pages.{$relatedSlug}"))
            ->filter()
            ->map(fn (array $entry): array => [
                'slug' => $entry['slug'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'teaser' => $entry['teaser'] ?? $entry['subheadline'],
            ])
            ->values()
            ->all();

        return view('alternatives.show', [
            'page' => $page,
            'related' => $related,
        ]);
    }
}
