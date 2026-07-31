        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-200 bg-white/95 p-3 shadow-[0_-8px_30px_-12px_rgba(24,24,27,0.25)] backdrop-blur md:hidden">
            <flux:textarea wire:model="decisionNote" rows="2" placeholder="Overall note (optional)…" class="mb-2" />
            <div class="flex gap-2">
                <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="requestChanges" wire:confirm="Request changes and send marks back to the agent?" class="min-w-0 flex-1 !bg-zinc-100 hover:!bg-zinc-200/80">Changes</flux:button>
                <flux:button variant="primary" icon="check" wire:click="approve" wire:confirm="Approve this pass? Resolved marks will be verified and the loop closes." class="min-w-0 flex-1">Approve</flux:button>
            </div>
        </div>
