{{-- What an assistant can and can't do once connected. Kept true to the tools:
     agents create reviews and report progress; approving and verifying are
     app-only tools a person uses (Visibility::App on decide_review / verify_mark). --}}
@props(['name' => 'Your assistant'])

<div {{ $attributes->class('grid gap-3 sm:grid-cols-2') }}>
    <div class="rounded-2xl bg-card p-4">
        <p class="text-sm font-medium text-zinc-900">{{ $name }} can</p>
        <ul class="mt-2 space-y-1.5 text-sm text-zinc-600">
            <li class="flex gap-2"><flux:icon.check variant="micro" class="mt-0.5 size-4 shrink-0 text-done" />Send screenshots, pages, PDFs and emails for review</li>
            <li class="flex gap-2"><flux:icon.check variant="micro" class="mt-0.5 size-4 shrink-0 text-done" />Read your marks and what you decided</li>
            <li class="flex gap-2"><flux:icon.check variant="micro" class="mt-0.5 size-4 shrink-0 text-done" />Say what it fixed, for you to check</li>
        </ul>
    </div>
    <div class="rounded-2xl bg-card p-4">
        <p class="text-sm font-medium text-zinc-900">Stays with you</p>
        <ul class="mt-2 space-y-1.5 text-sm text-zinc-600">
            <li class="flex gap-2"><flux:icon.hand-raised variant="micro" class="mt-0.5 size-4 shrink-0 text-zinc-400" />Approving, asking for changes, verifying a fix</li>
            <li class="flex gap-2"><flux:icon.hand-raised variant="micro" class="mt-0.5 size-4 shrink-0 text-zinc-400" />Other workspaces and anything outside ReviseMy</li>
            <li class="flex gap-2"><flux:icon.hand-raised variant="micro" class="mt-0.5 size-4 shrink-0 text-zinc-400" />Disconnecting, any time</li>
        </ul>
    </div>
</div>
