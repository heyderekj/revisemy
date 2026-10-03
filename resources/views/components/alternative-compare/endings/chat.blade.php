{{-- A screenshot pasted into a chat, opinions back, then the next pass. --}}
<div class="flex flex-col gap-3">
    <div class="max-w-[85%] self-end rounded-2xl rounded-br-md bg-zinc-200 p-2 text-sm text-zinc-800">
        <x-alternative-compare.shot muted class="w-40 max-w-full sm:w-48" />
        <p class="mt-2 px-1">What would you change here?</p>
    </div>
    <div class="flex gap-2">
        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-zinc-500"><flux:icon.sparkles variant="micro" class="size-3.5" /></span>
        <ul class="min-w-0 space-y-1 text-sm leading-relaxed text-zinc-600">
            @foreach (['Try a bolder hero font', 'Add social proof near the top', 'Consider a darker palette'] as $idea)
                <li class="flex gap-2"><span class="mt-2 size-1 shrink-0 rounded-full bg-zinc-300"></span>{{ $idea }}</li>
            @endforeach
        </ul>
    </div>
    <div class="max-w-[85%] self-end rounded-2xl rounded-br-md bg-zinc-200 px-3 py-2 text-sm text-zinc-800">
        Next day: did you fix the headline thing?
    </div>
    <div class="flex gap-2">
        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-zinc-500"><flux:icon.sparkles variant="micro" class="size-3.5" /></span>
        <p class="min-w-0 text-sm leading-relaxed text-zinc-600">Which headline do you mean? Here’s the latest version…</p>
    </div>
</div>
