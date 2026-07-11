@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ rtrim(config('app.url'), '/') }}/</loc>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>
</urlset>
