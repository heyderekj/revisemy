<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upgrade to Pro — ReviseMy</title>
    @vite(['resources/css/app.css'])
    @include('cashier::js')
</head>
<body class="min-h-dvh bg-zinc-50 text-zinc-900 antialiased">
    <main class="mx-auto flex min-h-dvh max-w-lg flex-col justify-center px-6 py-16">
        <p class="text-sm font-medium tracking-wide text-zinc-500">ReviseMy</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight">Upgrade to Pro</h1>
        <p class="mt-3 text-base leading-relaxed text-zinc-600">
            ${{ $priceUsd }}/mo · {{ $credits }} credits · same full capture quality.
            Paddle Checkout should open automatically — enter your email and pay there.
        </p>
        <button
            type="button"
            id="rm-open-paddle"
            class="mt-8 inline-flex items-center justify-center rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800"
        >
            Open checkout
        </button>
        <a href="{{ route('billing.cancel') }}" class="mt-6 text-sm font-medium text-zinc-600 underline underline-offset-4">Cancel</a>
    </main>
    <script>
        const options = @json($options);
        function openCheckout() {
            if (window.Paddle && typeof Paddle.Checkout?.open === 'function') {
                Paddle.Checkout.open(options);
            }
        }
        document.getElementById('rm-open-paddle')?.addEventListener('click', openCheckout);
        window.addEventListener('load', () => setTimeout(openCheckout, 300));
    </script>
</body>
</html>
