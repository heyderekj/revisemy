@props([
    'title' => null,
    'description' => null,
    'ogImage' => null,
    'ogUrl' => null,
    'canonical' => null,
    'robots' => 'index, follow',
    'schema' => 'page',
])

@php
    use App\Support\Seo;

    $siteName = config('seo.name');
    $siteUrl = rtrim(config('app.url'), '/');
    $pageTitle = $title ?? $siteName;
    $pageDescription = $description ?? config('seo.description');
    $pageOgImage = Seo::ogImageUrl($ogImage);
    $pageOgUrl = $ogUrl ?? url()->current();
    $pageCanonical = $canonical ?? $pageOgUrl;
    $keywords = implode(', ', config('seo.keywords', []));
    $mcpUrl = $siteUrl.config('seo.mcp_path');
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="author" content="{{ config('seo.author') }}">
<meta name="application-name" content="{{ $siteName }}">
<meta name="robots" content="{{ $robots }}">
<meta name="theme-color" content="{{ config('seo.theme_color') }}">
<meta name="color-scheme" content="light">
<link rel="canonical" href="{{ $pageCanonical }}">
<link rel="alternate" type="text/plain" href="{{ $siteUrl }}/llms.txt" title="LLM site index">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $pageOgUrl }}">
<meta property="og:image" content="{{ $pageOgImage }}">
<meta property="og:image:alt" content="{{ $siteName }} — {{ config('seo.tagline') }}">
<meta property="og:image:width" content="{{ config('seo.og_image_width', 1024) }}">
<meta property="og:image:height" content="{{ config('seo.og_image_height', 537) }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="{{ config('seo.twitter') }}">
<meta name="twitter:creator" content="{{ config('seo.twitter') }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="twitter:image" content="{{ $pageOgImage }}">
<meta name="twitter:image:alt" content="{{ $siteName }} — {{ config('seo.tagline') }}">

<meta name="apple-mobile-web-app-title" content="{{ $siteName }}">
<meta name="mobile-web-app-capable" content="yes">

@if ($schema === 'home')
    @php
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $siteUrl.'/#website',
                    'url' => $siteUrl.'/',
                    'name' => $siteName,
                    'description' => config('seo.description'),
                    'inLanguage' => 'en-US',
                    'publisher' => ['@id' => $siteUrl.'/#organization'],
                ],
                [
                    '@type' => 'Organization',
                    '@id' => $siteUrl.'/#organization',
                    'name' => $siteName,
                    'url' => $siteUrl.'/',
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => url('/images/app-icon.png'),
                    ],
                    'sameAs' => config('seo.same_as', []),
                ],
                [
                    '@type' => 'SoftwareApplication',
                    '@id' => $siteUrl.'/#software',
                    'name' => $siteName,
                    'url' => $siteUrl.'/',
                    'description' => config('seo.description'),
                    'applicationCategory' => config('seo.application_category'),
                    'operatingSystem' => 'Web',
                    'image' => $pageOgImage,
                    'featureList' => config('seo.features', []),
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'USD',
                    ],
                    'author' => [
                        '@type' => 'Person',
                        'name' => config('seo.author'),
                    ],
                    'isAccessibleForFree' => true,
                    'softwareVersion' => '1.0.0',
                    'downloadUrl' => config('seo.github'),
                    'installUrl' => $siteUrl.'/#setup',
                    'releaseNotes' => config('seo.github').'/releases',
                    'potentialAction' => [
                        '@type' => 'UseAction',
                        'target' => $siteUrl.'/#setup',
                        'name' => 'Get a try token and connect your agent',
                    ],
                ],
                [
                    '@type' => 'WebAPI',
                    '@id' => $siteUrl.'/#mcp',
                    'name' => $siteName.' MCP Server',
                    'description' => 'Laravel MCP endpoint for agents to create design reviews, fetch work packets, and continue human-in-the-loop checkups.',
                    'url' => $mcpUrl,
                    'documentation' => config('seo.github').'/blob/main/README.md',
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $pageOgUrl.'#webpage',
                    'url' => $pageOgUrl,
                    'name' => $pageTitle,
                    'description' => $pageDescription,
                    'isPartOf' => ['@id' => $siteUrl.'/#website'],
                    'about' => ['@id' => $siteUrl.'/#software'],
                    'inLanguage' => 'en-US',
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@elseif ($schema === 'page')
    @php
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $pageTitle,
            'description' => $pageDescription,
            'url' => $pageOgUrl,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => $siteUrl.'/',
            ],
            'inLanguage' => 'en-US',
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
