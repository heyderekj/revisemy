<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        use App\Support\Seo;

        $pageTitle = $title ?? 'ReviseMy';
        $pageDescription = $description ?? 'Visual feedback. With your agent.';
        $ogImage = Seo::ogImageUrl($ogImage ?? null);
        $ogUrl = $ogUrl ?? url()->current();
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="ReviseMy">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="{{ config('seo.og_image_width', 1200) }}">
    <meta property="og:image:height" content="{{ config('seo.og_image_height', 630) }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    <link rel="icon" href="{{ \App\Support\Seo::faviconUrl('/favicon-v9.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\Seo::faviconUrl('/images/favicon-32x32-v9.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ \App\Support\Seo::faviconUrl('/images/favicon-16x16-v9.png') }}">
    <link rel="apple-touch-icon" href="{{ \App\Support\Seo::faviconUrl('/images/apple-touch-icon-v9.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=caveat:500,600,700|instrument-sans:400,500,600,700|newsreader:400,500,600" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            color-scheme: light;
        }
    </style>
    <script>
        window.Flux = {
            applyAppearance () {
                document.documentElement.classList.remove('dark')
                window.localStorage.setItem('flux.appearance', 'light')
            }
        }
        window.Flux.applyAppearance()
    </script>
</head>
<body class="min-h-screen bg-[var(--color-paper)] text-[var(--color-ink)] antialiased">
    {{ $slot }}

    @fluxScripts
</body>
</html>
