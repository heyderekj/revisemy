<?php

use App\Models\Workspace;
use App\Support\DesignRules;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * The workspace's default design rules (a DESIGN.md), used when the agent
 * sends none with create_review. For people without a repo to keep one in:
 * paste the design system once and every review is checked against it.
 *
 * The workspace id is locked: only a page that already holds the token or
 * the signed-in session can render this for it.
 */
new class extends Component
{
    #[Locked]
    public int $workspaceId;

    public string $rules = '';

    public bool $saved = false;

    public function mount(): void
    {
        $this->rules = (string) Workspace::query()->whereKey($this->workspaceId)->value('design_rules');
    }

    public function save(): void
    {
        $this->validate(['rules' => 'nullable|string|max:'.DesignRules::MAX_LENGTH], [
            'rules.max' => 'Keep it under '.number_format(DesignRules::MAX_LENGTH).' characters.',
        ]);

        Workspace::query()->whereKey($this->workspaceId)->update(['design_rules' => DesignRules::clean($this->rules)]);
        $this->rules = (string) DesignRules::clean($this->rules);
        $this->saved = true;
    }

    #[Computed]
    public function ruleCount(): int
    {
        return count(DesignRules::rules($this->rules));
    }
};
?>

<div>
    <p class="mb-1 text-sm font-medium text-zinc-700">Design rules</p>
    <p class="mb-3 text-xs text-pretty text-muted-foreground">Paste your DESIGN.md or design system notes. When your agent doesn’t send its own, the second opinion checks every review against these first.</p>

    <form wire:submit="save" class="rounded-2xl bg-card p-4">
        <textarea
            wire:model="rules"
            x-on:input="$wire.saved = false"
            rows="6"
            maxlength="{{ \App\Support\DesignRules::MAX_LENGTH }}"
            aria-label="Design rules"
            placeholder="# Acme design system&#10;- Buttons are fully rounded&#10;- Never use pure black text; use zinc-900&#10;- One accent colour: #F5B700"
            class="block w-full resize-y rounded-xl bg-well px-3 py-2.5 font-mono text-[13px] leading-relaxed text-foreground outline-none ring-1 ring-transparent placeholder:text-zinc-400 focus:ring-zinc-400"
        ></textarea>
        @error('rules')
            <p class="mt-2 text-xs text-problem-ink">{{ $message }}</p>
        @enderror
        <div class="mt-3 flex items-center gap-3">
            <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover">Save</button>
            <p class="text-xs text-muted-foreground">
                @if ($saved)
                    Saved. New reviews use {{ $this->ruleCount }} {{ $this->ruleCount === 1 ? 'rule' : 'rules' }}.
                @elseif (trim($rules) !== '')
                    {{ $this->ruleCount }} {{ $this->ruleCount === 1 ? 'rule' : 'rules' }} found.
                @else
                    Empty, so only rules your agent sends are used.
                @endif
            </p>
        </div>
    </form>
</div>
