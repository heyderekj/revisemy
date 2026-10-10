@props([
    // What the client registered as. Anyone can register any name.
    'clientName',
    // Where the code goes. This, not the name, says who is connecting.
    'redirectUri' => null,
])

@php
    $assistant = \App\Support\AssistantCallback::identify($redirectUri);
    $name = \App\Support\AssistantCallback::displayName($clientName, $redirectUri);
    $lookalike = \App\Support\AssistantCallback::impersonates($clientName, $redirectUri);
    $returnsTo = \App\Support\ConnectLog::host($redirectUri);
@endphp

<div {{ $attributes }}>
    <div class="flex items-center gap-3">
        <x-revisemy-logo size="lg" />
        @if ($assistant && $assistant['icon'])
            <span class="text-sm text-muted-foreground" aria-hidden="true">+</span>
            <span class="inline-flex size-10 items-center justify-center rounded-xl bg-card text-foreground ring-1 ring-black/[0.06]">
                <x-host-icon :name="$assistant['icon']" size="lg" />
            </span>
        @endif
    </div>

    <h1 class="mt-6 text-2xl font-semibold text-foreground">Connect {{ $name }}</h1>

    @if ($lookalike)
        {{-- A client named like a known assistant whose code goes somewhere else. --}}
        <div class="mt-4 rounded-xl bg-problem-soft px-4 py-3 text-sm leading-relaxed text-problem-ink" role="alert">
            This app calls itself {{ \Illuminate\Support\Str::limit($clientName, 40) }}, but it sends you back to <span class="font-medium">{{ $returnsTo }}</span>. Only connect if you know that address.
        </div>
    @endif

    {{ $slot }}
</div>
