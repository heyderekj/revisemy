<?php

namespace App\Http\Controllers;

use App\Support\SkillPacks;
use Illuminate\View\View;

class SkillsController extends Controller
{
    public function index(): View
    {
        return view('skills.index', [
            'categories' => SkillPacks::categories(),
            'community' => config('skills.community', []),
            'example' => config('skills.review_example'),
        ]);
    }

    public function show(string $slug): View
    {
        $page = SkillPacks::pages()[$slug] ?? null;

        abort_unless(is_array($page), 404);

        return view('guides.show', [
            'page' => $page,
        ]);
    }
}
