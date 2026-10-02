@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
@php($base = rtrim(config('app.url'), '/'))
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ $base }}/</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ $base }}/connect</loc>
        <changefreq>monthly</changefreq>
        <priority>0.9</priority>
    </url>
    @foreach (\App\Support\MarketingPages::all() as $page)
    <url>
        <loc>{{ $base }}{{ $page['href'] }}</loc>
        <changefreq>monthly</changefreq>
        <priority>{{ substr_count($page['href'], '/') > 1 ? '0.8' : '0.85' }}</priority>
    </url>
    @endforeach
    <url>
        <loc>{{ $base }}/privacy</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ $base }}/terms</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
</urlset>
