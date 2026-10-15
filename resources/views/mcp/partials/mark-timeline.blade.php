{{-- The mark's timeline, inline: the same list as the web's
     components/mark-timeline, drawn from pin.timeline (App\Support\MarkTimeline).
     $pin is the Alpine expression for the mark. --}}
<div class="min-w-0" x-show="{{ $pin }} && ({{ $pin }}.timeline || []).length > 1">
    <div class="mb-2 inline-flex gap-0.5 rounded-full bg-well p-0.5" role="group" aria-label="Show on the timeline" x-show="hasSteps({{ $pin }})">
        <button type="button" class="h-6 rounded-full px-2.5 text-[11px] font-medium transition-colors" :class="talkOnly ? 'text-muted-foreground hover:text-zinc-900' : 'bg-raised text-zinc-900 shadow-xs'" @click="talkOnly = false" :aria-pressed="!talkOnly">Everything</button>
        <button type="button" class="h-6 rounded-full px-2.5 text-[11px] font-medium transition-colors" :class="talkOnly ? 'bg-raised text-zinc-900 shadow-xs' : 'text-muted-foreground hover:text-zinc-900'" @click="talkOnly = true" :aria-pressed="talkOnly">Talk only</button>
    </div>
    <ol class="min-w-0">
        <template x-for="(entry, i) in timelineFor({{ $pin }})" :key="i">
            <li class="flex gap-2.5">
                <span class="flex w-3 shrink-0 flex-col items-center" aria-hidden="true">
                    <span class="shrink-0 rounded-full" :class="[actorDot(entry.actor), isTalk(entry) ? 'mt-1.5 size-2.5' : 'mt-2 size-1.5']"></span>
                    <span class="mt-1 w-0.5 grow rounded-full bg-zinc-200" x-show="i < timelineFor({{ $pin }}).length - 1"></span>
                </span>
                <div class="min-w-0 flex-1 pb-3">
                    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold leading-4 whitespace-nowrap" :class="[actorPill(entry.actor), entry.actor === 'agent' ? 'pl-1.5' : '']">
                            <template x-if="entry.actor === 'agent'">
                                <svg class="size-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><rect x="4" y="4" width="8" height="8" rx="1.5"></rect><path d="M6 1.5v2M10 1.5v2M6 12.5v2M10 12.5v2M1.5 6h2M1.5 10h2M12.5 6h2M12.5 10h2"></path></svg>
                            </template>
                            <span x-text="actorLabel(entry)"></span>
                        </span>
                        <span class="text-zinc-600" x-text="entry.verb"></span>
                        <time class="text-zinc-400" :datetime="entry.at" x-show="entry.at" x-text="'· ' + ago(entry.at)"></time>
                    </p>
                    <p class="mt-1 rounded-xl px-2.5 py-1.5 text-sm leading-relaxed text-pretty whitespace-pre-line text-zinc-800" :class="entry.actor === 'agent' ? 'bg-sky-50' : 'bg-well'" x-show="entry.body" x-text="entry.body"></p>
                    <template x-if="entry.after_screenshot_url && entry.after_screenshot_url !== {{ $pin }}.after_screenshot_url">
                        <img class="mt-1.5 h-12 w-20 rounded-lg object-cover ring-1 ring-emerald-300" :src="entry.after_screenshot_url" alt="After image from your agent" loading="lazy">
                    </template>
                </div>
            </li>
        </template>
    </ol>
</div>
