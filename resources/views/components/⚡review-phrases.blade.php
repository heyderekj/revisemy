<?php

use App\Models\Workspace;
use App\Support\AssistantPhrases;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Your own words for asking an assistant to use ReviseMy, and words that
 * shouldn't. Assistants already know "review", "check", "proof" and the
 * like (AssistantPhrases::WHEN); these are added to the instructions every
 * connected assistant reads when a chat starts. Like muted words, but both
 * ways.
 *
 * The workspace id is locked: only a page that already holds the token or
 * the signed-in session can render this for it.
 */
new class extends Component
{
    #[Locked]
    public int $workspaceId;

    public string $useDraft = '';

    public string $skipDraft = '';

    #[Computed]
    public function workspace(): ?Workspace
    {
        return Workspace::query()->find($this->workspaceId);
    }

    /**
     * @return array{use: list<string>, skip: list<string>}
     */
    #[Computed]
    public function phrases(): array
    {
        return AssistantPhrases::for($this->workspace);
    }

    public function add(string $kind): void
    {
        if (! in_array($kind, AssistantPhrases::KINDS, true) || ! $this->workspace) {
            return;
        }

        $draft = $kind === 'use' ? 'useDraft' : 'skipDraft';
        $phrase = AssistantPhrases::clean($this->{$draft});
        $phrases = $this->phrases;

        $this->resetErrorBag($draft);

        if ($phrase === null) {
            return;
        }

        if (count($phrases[$kind]) >= AssistantPhrases::MAX) {
            $this->addError($draft, 'That’s '.AssistantPhrases::MAX.'. Remove one to add another.');

            return;
        }

        if (! in_array(mb_strtolower($phrase), array_map('mb_strtolower', $phrases[$kind]), true)) {
            $phrases[$kind][] = $phrase;
            $this->save($phrases);
        }

        $this->{$draft} = '';
    }

    public function remove(string $kind, int $index): void
    {
        if (! in_array($kind, AssistantPhrases::KINDS, true) || ! $this->workspace) {
            return;
        }

        $phrases = $this->phrases;
        unset($phrases[$kind][$index]);
        $phrases[$kind] = array_values($phrases[$kind]);
        $this->save($phrases);
    }

    /**
     * @param  array{use: list<string>, skip: list<string>}  $phrases
     */
    private function save(array $phrases): void
    {
        $this->workspace->forceFill(['assistant_phrases' => $phrases])->save();
        unset($this->workspace, $this->phrases);
    }
};
?>

<div>
    <p class="mb-1 text-sm font-medium text-zinc-700">Words that start a review</p>
    <p class="mb-3 text-xs text-pretty text-muted-foreground">Assistants already reach for ReviseMy when you say review, check, proof, mark up or get feedback, and leave it out of code review. Add your own words either way. A new chat picks them up.</p>

    <div class="grid gap-3 sm:grid-cols-2">
        @foreach (['use' => ['Also when I say', 'e.g. ship check'], 'skip' => ['Never when I say', 'e.g. quick look']] as $kind => [$label, $placeholder])
            @php($draft = $kind === 'use' ? 'useDraft' : 'skipDraft')
            <div class="rounded-2xl bg-card p-4">
                <p class="text-xs font-medium text-zinc-700">{{ $label }}</p>

                @if ($this->phrases[$kind] !== [])
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($this->phrases[$kind] as $index => $phrase)
                            <li class="inline-flex h-7 items-center gap-1 rounded-full bg-chip pl-2.5 pr-1 text-xs text-zinc-800" wire:key="{{ $kind }}-{{ $index }}-{{ $phrase }}">
                                {{ $phrase }}
                                <button type="button" class="inline-flex size-5 items-center justify-center rounded-full text-zinc-500 hover:bg-chip-hover hover:text-zinc-900" wire:click="remove('{{ $kind }}', {{ $index }})" aria-label="Remove {{ $phrase }}">
                                    <flux:icon.x-mark variant="micro" class="size-3.5" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form class="mt-3 flex gap-2" wire:submit="add('{{ $kind }}')">
                    <input
                        type="text"
                        wire:model="{{ $draft }}"
                        maxlength="{{ \App\Support\AssistantPhrases::MAX_LENGTH }}"
                        placeholder="{{ $placeholder }}"
                        aria-label="{{ $label }}"
                        class="h-8 min-w-0 flex-1 rounded-full bg-well px-3 text-sm text-foreground outline-none ring-1 ring-transparent placeholder:text-zinc-400 focus:ring-zinc-400"
                    >
                    <button type="submit" class="inline-flex h-8 shrink-0 items-center rounded-full bg-chip px-3 text-xs font-medium text-zinc-700 transition-colors hover:bg-chip-hover">Add</button>
                </form>
                @error($draft)
                    <p class="mt-2 text-xs text-problem-ink">{{ $message }}</p>
                @enderror
            </div>
        @endforeach
    </div>
</div>
