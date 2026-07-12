@props([
    'title' => null,
    'description' => null,
    'ogImage' => null,
    'ogUrl' => null,
    'canonical' => null,
    'robots' => 'index, follow',
    'schema' => 'page',
])

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <x-seo-head
        :title="$title"
        :description="$description"
        :og-image="$ogImage"
        :og-url="$ogUrl"
        :canonical="$canonical"
        :robots="$robots"
        :schema="$schema"
    />

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png">

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

    @if (config('seo.fathom_site_id'))
        <!-- Fathom - beautiful, simple website analytics -->
        <script src="https://cdn.usefathom.com/script.js" data-site="{{ config('seo.fathom_site_id') }}" data-auto="false" defer></script>
        <script>
            (function () {
                function shouldTrackPageview() {
                    return !/^\/r\//.test(window.location.pathname);
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
<body class="min-h-screen bg-[var(--color-paper)] text-[var(--color-ink)] antialiased">
    {{ $slot }}

    @fluxScripts
</body>
</html>
