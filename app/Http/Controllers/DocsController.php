<?php

namespace App\Http\Controllers;

use App\Support\DeveloperDocs;
use Illuminate\View\View;

class DocsController extends Controller
{
    public function index(): View
    {
        return $this->page('index');
    }

    public function show(string $slug): View
    {
        // The overview lives at /docs, not /docs/index.
        abort_if($slug === 'index', 404);

        return $this->page($slug);
    }

    protected function page(string $slug): View
    {
        $page = DeveloperDocs::find($slug);

        abort_if($page === null, 404);

        return view('docs.show', [
            'page' => $page,
            'pages' => DeveloperDocs::pages(),
            'rendered' => DeveloperDocs::render($slug),
            'neighbours' => DeveloperDocs::neighbours($slug),
            'editUrl' => DeveloperDocs::editUrl($slug),
        ]);
    }
}
