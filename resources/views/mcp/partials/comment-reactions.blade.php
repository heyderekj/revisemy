<div class="mt-1.5 flex flex-wrap items-center gap-1" x-data="{ adding: false }" @click.outside="adding = false"
    @keydown.escape.stop="if (adding) { adding = false; $refs.add.focus(); }">
    <template x-for="(option, reaction) in reactionOptions" :key="reaction">
        <button type="button"
            x-show="entry.reactions?.[reaction]?.count || (adding && payload.comments_enabled)"
            class="inline-flex h-7 items-center gap-1 rounded-full px-2 text-xs tabular-nums transition-colors disabled:opacity-60"
            :class="entry.reactions?.[reaction]?.selected ? 'bg-key/20 ring-1 ring-inset ring-key/60' : 'bg-chip hover:bg-chip-hover'"
            :aria-label="entry.reactions?.[reaction]?.count ? option[1] + ' (' + entry.reactions[reaction].count + ')' : option[1]"
            :aria-pressed="!!entry.reactions?.[reaction]?.selected"
            :disabled="busy || !payload.comments_enabled"
            @click="react(entry, reaction, !entry.reactions?.[reaction]?.selected)">
            <span aria-hidden="true" x-text="option[0]"></span>
            <span x-show="entry.reactions?.[reaction]?.count" x-text="entry.reactions?.[reaction]?.count"></span>
        </button>
    </template>
    <button type="button" class="inline-flex size-7 items-center justify-center rounded-full text-zinc-500 transition-colors hover:bg-chip hover:text-zinc-900"
        x-ref="add" x-show="payload.comments_enabled" :aria-expanded="adding.toString()" @click="adding = !adding" aria-label="Add reaction">
        <flux:icon.face-smile variant="micro" class="size-4" />
    </button>
</div>
