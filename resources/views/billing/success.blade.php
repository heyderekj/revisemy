<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pro unlocked — ReviseMy</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased">
    <main class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6 py-16">
        <p class="text-sm font-medium tracking-wide text-zinc-500">ReviseMy</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">You’re on Pro</h1>
        <p class="mt-3 text-base leading-relaxed text-zinc-600">
            Thanks{{ $email ? ' — receipt comes from Paddle to '.$email : '' }}.
            Your workspace now has {{ (int) config('billing.plans.pro.credits', 100) }} credits for this month (full capture quality).
        </p>
        <p class="mt-6 text-base leading-relaxed text-zinc-600">
            Return to your agent and continue — call <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 text-sm">create_review</code> again, or <code class="rounded bg-zinc-200/80 px-1.5 py-0.5 text-sm">get_billing</code> to confirm credits.
        </p>
        <a href="/" class="mt-10 inline-flex text-sm font-medium text-zinc-900 underline underline-offset-4">Back to homepage</a>
    </main>
</body>
</html>
