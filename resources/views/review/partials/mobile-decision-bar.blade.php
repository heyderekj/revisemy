        {{-- The decision, under the thumb. The note stays folded until it's wanted. --}}
        <div class="material-chrome fixed inset-x-0 bottom-0 z-30 p-3 md:hidden" x-data="{ note: false }">
            <div x-show="note" x-cloak class="mb-2">
                <flux:textarea wire:model="decisionNote" rows="2" placeholder="Note for your agent (optional)" />
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-chip text-zinc-600 transition-colors hover:bg-chip-hover" x-on:click="note = ! note" x-bind:aria-expanded="note.toString()" aria-label="Add a note for your agent">
                    <flux:icon.chat-bubble-bottom-center-text variant="micro" class="size-4" />
                </button>
                <flux:button variant="ghost" icon="arrow-uturn-left" x-on:click="$dispatch('rm-decide', { kind: 'changes' })" class="min-w-0 flex-1 !bg-chip hover:!bg-chip-hover">Changes</flux:button>
                <flux:button variant="primary" icon="check" x-on:click="$dispatch('rm-decide', { kind: 'approve' })" class="min-w-0 flex-1">Approve</flux:button>
            </div>
        </div>
