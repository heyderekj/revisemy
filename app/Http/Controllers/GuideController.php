<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class GuideController extends Controller
{
    public function show(string $slug): View
    {
        $page = config("guides.pages.{$slug}");

        abort_unless(is_array($page), 404);

        return view('guides.show', [
            'page' => $page,
        ]);
    }
}
