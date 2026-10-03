@props([
    'title' => null,
    'description' => null,
    'keywords' => null,
    'ogImage' => null,
    'ogUrl' => null,
    'canonical' => null,
    'robots' => 'index, follow',
    'schema' => 'page',
])

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Light or dark before first paint: the visitor's choice, else the system's. --}}
    @fluxAppearance

    <x-seo-head
        :title="$title"
        :description="$description"
        :keywords="$keywords"
        :og-image="$ogImage"
        :og-url="$ogUrl"
        :canonical="$canonical"
        :robots="$robots"
        :schema="$schema"
    />

    <link rel="icon" href="{{ \App\Support\Seo::faviconUrl('/favicon-v9.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\Seo::faviconUrl('/images/favicon-32x32-v9.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ \App\Support\Seo::faviconUrl('/images/favicon-16x16-v9.png') }}">
    <link rel="apple-touch-icon" href="{{ \App\Support\Seo::faviconUrl('/images/apple-touch-icon-v9.png') }}">

    <link rel="preload" href="/fonts/figtree-latin-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/figtree-latin-500.woff2" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if (config('seo.fathom_site_id'))
        <!-- Fathom - beautiful, simple website analytics -->
        <script src="https://cdn.usefathom.com/script.js" data-site="{{ config('seo.fathom_site_id') }}" data-auto="false" defer></script>
        <script>
            (function () {
                // Review links carry a secret token and billing links a workspace id: not for a third party.
                function shouldTrackPageview() {
                    return !/^\/(r|billing\/(manage|checkout))\//.test(window.location.pathname);
                }

                function trackPageview() {
                    if (!window.fathom || !shouldTrackPageview()) {
                        return;
                    }

                    fathom.trackPageview();
                }

                window.addEventListener('load', trackPageview);
            })();
        </script>
        <!-- / Fathom -->
    @endif
</head>
<body class="min-h-screen bg-background text-foreground antialiased">
    {{ $slot }}

    @fluxScripts
</body>
</html>
