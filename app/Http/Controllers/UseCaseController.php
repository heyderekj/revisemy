<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class UseCaseController extends Controller
{
    public function index(): View
    {
        $pages = collect(config('use-cases.pages', []))
            ->map(fn (array $entry): array => [
                'slug' => $entry['slug'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'headline' => $entry['headline'],
                'description' => $entry['description'],
            ])
            ->values()
            ->all();

        $audiences = collect(config('use-cases.audiences', []))
            ->map(fn (array $entry): array => [
                'slug' => $entry['slug'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'headline' => $entry['headline'],
                'description' => $entry['description'],
            ])
            ->values()
            ->all();

        return view('use-cases.index', [
            'pages' => $pages,
            'audiences' => $audiences,
        ]);
    }

    public function show(string $slug): View
    {
        $page = config("use-cases.pages.{$slug}")
            ?? config("use-cases.audiences.{$slug}");

        abort_unless(is_array($page), 404);

        $related = collect(config('use-cases.pages', []))
            ->except($slug)
            ->map(fn (array $entry): array => [
                'slug' => $entry['slug'],
                'label' => $entry['label'],
                'icon' => $entry['icon'],
                'headline' => $entry['headline'],
            ])
            ->values()
            ->all();

        return view('use-cases.show', [
            'page' => $page,
            'related' => $related,
        ]);
    }
}
