@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ rtrim(config('app.url'), '/') }}/</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ rtrim(config('app.url'), '/') }}/for</loc>
        <changefreq>monthly</changefreq>
        <priority>0.85</priority>
    </url>
    @foreach (config('use-cases.pages', []) as $slug => $page)
    <url>
        <loc>{{ rtrim(config('app.url'), '/') }}/for/{{ $slug }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    @endforeach
    @foreach (config('use-cases.audiences', []) as $slug => $page)
    <url>
        <loc>{{ rtrim(config('app.url'), '/') }}/for/{{ $slug }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    @endforeach
    @foreach (config('guides.pages', []) as $slug => $page)
    <url>
        <loc>{{ rtrim(config('app.url'), '/') }}{{ $page['path'] }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.85</priority>
    </url>
    @endforeach
</urlset>
