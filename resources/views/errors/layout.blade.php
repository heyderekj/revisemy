{{-- Error pages: no Livewire, no database, nothing that could fail again while
     saying something failed. Same tokens, same shell as the review. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @fluxAppearance
    <title>@yield('title') · ReviseMy</title>
    <link rel="icon" href="/favicon-v9.ico" sizes="any">
    @vite(['resources/css/app.css'])
</head>
<body class="rm-desk flex min-h-svh flex-col antialiased">
    <main class="rm-shell items-center justify-center px-6 py-16 text-center">
        <div class="hatch flex size-16 items-center justify-center rounded-2xl text-zinc-300">
            <img src="/images/app-icon-v9.png" alt="ReviseMy" width="40" height="40" class="size-10">
        </div>
        <p class="mt-6 text-sm tabular-nums text-muted-foreground">@yield('code')</p>
        <h1 class="mt-2 text-2xl font-semibold text-foreground">@yield('title')</h1>
        <p class="mt-2 max-w-sm text-sm leading-relaxed text-pretty text-muted-foreground">@yield('message')</p>
        <a href="/" class="btn-quiet mt-8 inline-flex h-9 items-center rounded-full px-4 text-sm font-medium">Back to ReviseMy</a>
    </main>
</body>
</html>
