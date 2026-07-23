<footer {{ $attributes->class('relative border-t border-zinc-200 px-[var(--rm-pad)] py-12 text-sm text-zinc-500') }}>
    <x-cross-mark left="0" top="0" />
    <x-cross-mark left="100%" top="0" />
    <div class="flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between">
        <div class="max-w-sm space-y-3">
            <p class="text-zinc-400">
                <a href="https://osaasy.dev/" target="_blank" rel="noreferrer" class="transition hover:text-zinc-900">O’Saasy</a>
                · Laravel + Livewire Flux · Built for Laravel Cloud
            </p>
            <p class="flex flex-wrap gap-x-4 gap-y-2">
                <a href="/privacy" class="transition hover:text-zinc-900">Privacy</a>
                <a href="/terms" class="transition hover:text-zinc-900">Terms</a>
                <a href="/reviews" class="transition hover:text-zinc-900">Recent reviews</a>
                <a href="/changelog" class="transition hover:text-zinc-900">Changelog</a>
            </p>
        </div>
        <p class="shrink-0 text-zinc-400 sm:text-right">
            © {{ date('Y') }} Testament Made, LLC
        </p>
    </div>
</footer>
