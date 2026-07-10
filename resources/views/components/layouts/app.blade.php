<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ReviseMy' }}</title>
    <meta name="description" content="{{ $description ?? 'Pin feedback for your agent. Human-in-the-loop design review on Laravel Cloud.' }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700|newsreader:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-[var(--color-paper)] text-[var(--color-ink)] antialiased">
    {{ $slot }}

    @fluxScripts
</body>
</html>
