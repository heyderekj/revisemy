<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function llms(): Response
    {
        return response()
            ->view('seo.llms')
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function sitemap(): Response
    {
        return response()
            ->view('seo.sitemap')
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(): Response
    {
        return response()
            ->view('seo.robots')
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
