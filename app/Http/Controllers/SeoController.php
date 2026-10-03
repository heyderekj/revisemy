<?php

namespace App\Http\Controllers;

use App\Support\BrandAssets;
use App\Support\McpCatalog;
use App\Support\PageMarkdown;
use Illuminate\Http\JsonResponse;
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

    /** Every page and the tool reference in one file, for agents that read it all at once. */
    public function llmsFull(): Response
    {
        return response()
            ->view('seo.llms-full')
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /** The markdown twin of a public page: /board.md, /for/websites.md, /index.md. */
    public function markdown(string $path): Response
    {
        $markdown = PageMarkdown::for($path === 'index' ? '/' : $path);

        abort_if($markdown === null, 404);

        return response($markdown)->header('Content-Type', 'text/markdown; charset=utf-8');
    }

    /** What an MCP client needs to know before connecting, read from the server itself. */
    public function serverCard(): JsonResponse
    {
        return response()->json([
            'name' => 'io.github.heyderekj/revisemy',
            'title' => config('seo.name'),
            'description' => config('seo.description'),
            'version' => config('revisemy.version'),
            'websiteUrl' => rtrim((string) config('app.url'), '/'),
            'repository' => ['url' => config('seo.github'), 'source' => 'github'],
            'license' => 'O’Saasy',
            'icons' => [['src' => BrandAssets::appIconUrl(), 'mimeType' => 'image/png', 'sizes' => ['64x64']]],
            'remotes' => [[
                'type' => 'streamable-http',
                'url' => McpCatalog::endpoint(),
            ]],
            'authentication' => [
                'oauth' => [
                    'protectedResourceMetadata' => url('/.well-known/oauth-protected-resource'),
                    'dynamicClientRegistration' => true,
                ],
                'bearer' => [
                    'description' => 'A free try token: POST '.url('/api/try-token').', then send Authorization: Bearer {token}.',
                ],
            ],
            'tools' => McpCatalog::tools(),
            'prompts' => McpCatalog::prompts(),
            'nextActions' => McpCatalog::nextActions(),
            'docs' => [
                'llms' => url('/llms.txt'),
                'llmsFull' => url('/llms-full.txt'),
            ],
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
