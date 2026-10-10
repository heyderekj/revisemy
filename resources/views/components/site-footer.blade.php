<footer {{ $attributes->class('relative px-[var(--rm-pad)] py-12 text-sm text-zinc-500') }}>
    <div class="flex flex-col gap-6 rounded-2xl bg-card p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
        <p class="flex flex-wrap gap-x-4 gap-y-2">
            <a href="/security" class="transition-colors hover:text-zinc-900">Security</a>
            <a href="/privacy" class="transition-colors hover:text-zinc-900">Privacy</a>
            <a href="/terms" class="transition-colors hover:text-zinc-900">Terms</a>
            <a href="/reviews" class="transition-colors hover:text-zinc-900">Your reviews</a>
            <a href="/changelog" class="transition-colors hover:text-zinc-900">Changelog</a>
            <a href="/docs" class="transition-colors hover:text-zinc-900">Developer docs</a>
            <a href="https://github.com/heyderekj/revisemy" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">GitHub ↗</a>
        </p>
        <p class="shrink-0 text-zinc-400">
            © {{ date('Y') }} <a href="https://testamentmade.com" target="_blank" rel="noreferrer" class="transition-colors hover:text-zinc-900">Testament Made, LLC</a>
        </p>
    </div>
</footer>
