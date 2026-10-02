<footer {{ $attributes->class('relative px-[var(--rm-pad)] py-12 text-sm text-zinc-500') }}>
    <div class="flex flex-col gap-8 rounded-2xl bg-card p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8">
        <div class="max-w-sm space-y-4">
            <p class="flex flex-wrap gap-x-4 gap-y-2">
                <a href="/privacy" class="transition-colors hover:text-zinc-900">Privacy</a>
                <a href="/terms" class="transition-colors hover:text-zinc-900">Terms</a>
                <a href="/reviews" class="transition-colors hover:text-zinc-900">Recent reviews</a>
                <a href="/changelog" class="transition-colors hover:text-zinc-900">Changelog</a>
                <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">GitHub ↗</a>
            </p>
            <p class="text-zinc-400">
                Open source under <a href="https://osaasy.dev/" target="_blank" rel="noreferrer" class="underline decoration-zinc-300 underline-offset-2 transition-colors hover:text-zinc-900">O’Saasy</a>.
            </p>
        </div>
        <div class="flex flex-col gap-4 sm:items-end">
            <x-appearance-toggle />
            <p class="text-zinc-400 sm:text-right">
                © {{ date('Y') }} <a href="https://testamentmade.com" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">Testament Made, LLC</a>
                <span aria-hidden="true">·</span>
                @if (config('revisemy.koati_url'))
                    From the makers of <a href="{{ config('revisemy.koati_url') }}" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">Koati</a>
                @else
                    From the makers of Koati
                @endif
            </p>
        </div>
    </div>
</footer>
