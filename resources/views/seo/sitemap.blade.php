@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
@php($base = rtrim(config('app.url'), '/'))
{{-- The newest release date stands in for every page: copy ships with releases. --}}
@php($lastmod = collect(config('changelog.entries', []))->pluck('date')->filter()->max() ?? now()->toDateString())
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ $base }}/</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
    @foreach (\App\Support\MarketingPages::all() as $page)
    <url>
        <loc>{{ $base }}{{ $page['href'] }}</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>{{ substr_count($page['href'], '/') > 1 ? '0.8' : '0.85' }}</priority>
    </url>
    @endforeach
    @if (config('billing.pricing_enabled'))
    <url>
        <loc>{{ $base }}/upgrade</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endif
    <url>
        <loc>{{ $base }}/privacy</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc>{{ $base }}/terms</loc>
        <lastmod>{{ $lastmod }}</lastmod>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>
</urlset>
