<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage billing — ReviseMy</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased">
    <main class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6 py-16">
        <p class="text-sm font-medium tracking-wide text-zinc-500">ReviseMy</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Billing</h1>
        <p class="mt-3 text-base leading-relaxed text-zinc-600">
            Plan: <strong>{{ $status['plan_name'] }}</strong>
            · Credits: {{ $status['credits_remaining'] }} / {{ $status['credits_grant'] }}
        </p>
        @if (session('status'))
            <p class="mt-4 text-sm text-emerald-700">{{ session('status') }}</p>
        @endif
        @if (session('error'))
            <p class="mt-4 text-sm text-rose-700">{{ session('error') }}</p>
        @endif
        @if ($subscribed)
            <p class="mt-4 text-sm leading-relaxed text-zinc-600">
                Receipts and payment method updates come from Paddle (merchant of record).
                Cancel below to stop renewal — you keep Pro until the current period ends.
            </p>
            <form method="post" action="{{ URL::temporarySignedRoute('billing.cancel-subscription', now()->addHours(6), ['workspace' => $workspace->public_id]) }}" class="mt-8">
                @csrf
                <button type="submit" class="inline-flex rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-800 transition hover:bg-zinc-50">
                    Cancel Pro
                </button>
            </form>
        @else
            <p class="mt-4 text-sm text-zinc-600">You’re on Free. Ask your agent to call <code class="rounded bg-zinc-200/80 px-1">create_checkout</code> to upgrade.</p>
        @endif
        <a href="/" class="mt-10 inline-flex text-sm font-medium text-zinc-900 underline underline-offset-4">Back to homepage</a>
    </main>
</body>
</html>
