{{-- The home hero: a fictional review and its video tour. --}}
<section
    class="rm-hero-loop relative"
    aria-label="Fieldnote review preview"
    x-data="{
        view: 'review',
        get nextView() { return this.view === 'review' ? 'board' : 'review' },
        select(view, focus = false) {
            this.view = view;
            if (focus) this.$refs[view].focus();
        },
    }"
>
    <div class="relative">
        <div
            role="tablist"
            aria-label="Fieldnote preview views"
            class="absolute top-3 left-3 z-20 inline-flex gap-0.5 rounded-full bg-chip p-1 shadow-xs ring-1 ring-border sm:top-4 sm:left-5"
            x-cloak
            x-on:keydown.left.prevent="select(nextView, true)"
            x-on:keydown.right.prevent="select(nextView, true)"
            x-on:keydown.home.prevent="select('review', true)"
            x-on:keydown.end.prevent="select('board', true)"
        >
            @foreach (['review' => 'Review', 'board' => 'Board'] as $key => $label)
                <button
                    type="button"
                    role="tab"
                    id="rm-preview-tab-{{ $key }}"
                    aria-controls="rm-preview-panel-{{ $key }}"
                    x-ref="{{ $key }}"
                    x-on:click="select('{{ $key }}')"
                    x-bind:aria-selected="(view === '{{ $key }}').toString()"
                    x-bind:tabindex="view === '{{ $key }}' ? 0 : -1"
                    class="min-h-7 rounded-full px-3 text-xs font-medium text-zinc-600 hover:text-zinc-900 aria-selected:bg-raised aria-selected:text-zinc-900 aria-selected:shadow-xs"
                >{{ $label }}</button>
            @endforeach
        </div>
        <div role="tabpanel" id="rm-preview-panel-review" aria-labelledby="rm-preview-tab-review" tabindex="0" x-show="view === 'review'">
            <x-review-mock id="rm-fieldnote-preview" sample="website" demo class="!pt-11 pb-8 sm:!pt-12" />
        </div>
        <div role="tabpanel" id="rm-preview-panel-board" aria-labelledby="rm-preview-tab-board" tabindex="0" x-show="view === 'board'" x-cloak>
            <x-review-mock sample="website" demo board class="!pt-11 pb-8 sm:!pt-12" />
        </div>
        <x-pitch-video class="absolute bottom-2 left-3 z-20 rounded-full shadow-xs ring-1 ring-border sm:bottom-3 sm:left-5" />
    </div>
</section>
