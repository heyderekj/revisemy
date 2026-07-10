<?php

use App\Models\Annotation;
use App\Models\Review;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public string $token;

    public Review $review;

    public int $activeScreenshotIndex = 0;

    public string $draftBody = '';

    public string $draftSeverity = Annotation::SEVERITY_MUST_FIX;

    public ?float $pendingX = null;

    public ?float $pendingY = null;

    public string $decisionNote = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadReview();
    }

    public function loadReview(): void
    {
        $this->review = Review::query()
            ->where('token', $this->token)
            ->with(['screenshots.annotations'])
            ->firstOrFail();
    }

    public function selectScreenshot(int $index): void
    {
        $this->activeScreenshotIndex = $index;
        $this->cancelPin();
    }

    public function startPin(float $x, float $y): void
    {
        if (! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->pendingX = max(0, min(1, $x));
        $this->pendingY = max(0, min(1, $y));
        $this->draftBody = '';
        $this->draftSeverity = Annotation::SEVERITY_MUST_FIX;
    }

    public function cancelPin(): void
    {
        $this->pendingX = null;
        $this->pendingY = null;
        $this->draftBody = '';
    }

    public function savePin(): void
    {
        if (! $this->review->isOpenForFeedback() || $this->pendingX === null || $this->pendingY === null) {
            return;
        }

        $this->validate([
            'draftBody' => ['required', 'string', 'max:2000'],
            'draftSeverity' => ['required', 'in:must-fix,nit'],
        ], [
            'draftBody.required' => 'Leave a note on this spot.',
        ]);

        $screenshot = $this->review->screenshots->values()->get($this->activeScreenshotIndex);

        if (! $screenshot) {
            return;
        }

        $number = ((int) $screenshot->annotations()->max('number')) + 1;

        $screenshot->annotations()->create([
            'x' => $this->pendingX,
            'y' => $this->pendingY,
            'severity' => $this->draftSeverity,
            'body' => $this->draftBody,
            'number' => $number,
        ]);

        $this->cancelPin();
        $this->loadReview();
    }

    public function deletePin(int $annotationId): void
    {
        if (! $this->review->isOpenForFeedback()) {
            return;
        }

        $annotation = Annotation::query()
            ->whereKey($annotationId)
            ->whereHas('screenshot', fn ($q) => $q->where('review_id', $this->review->id))
            ->first();

        $annotation?->delete();
        $this->loadReview();
    }

    public function approve(): void
    {
        $this->decide(Review::STATUS_APPROVED);
    }

    public function requestChanges(): void
    {
        $this->decide(Review::STATUS_CHANGES_REQUESTED);
    }

    protected function decide(string $status): void
    {
        if (! $this->review->isOpenForFeedback()) {
            return;
        }

        $this->validate([
            'decisionNote' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->review->update([
            'status' => $status,
            'decision_note' => $this->decisionNote ?: null,
            'decision_at' => now(),
        ]);

        $this->loadReview();
    }

    public function getActiveScreenshotProperty()
    {
        return $this->review->screenshots->values()->get($this->activeScreenshotIndex);
    }
};
?>

<div class="min-h-screen">
    <header class="border-b border-zinc-200 bg-white/90 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    <a href="/" class="font-semibold text-teal-700 hover:underline dark:text-teal-400">ReviseMy</a>
                    <span>/</span>
                    <span class="truncate">{{ $review->title }}</span>
                </div>
                <p class="mt-1 text-sm text-zinc-500">
                    @if ($review->effectiveStatus() === 'pending')
                        Waiting on your eye
                    @elseif ($review->effectiveStatus() === 'changes_requested')
                        Changes requested
                    @elseif ($review->effectiveStatus() === 'approved')
                        Looks good — approved
                    @else
                        This review link expired
                    @endif
                </p>
            </div>

            @if ($review->isOpenForFeedback())
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button variant="ghost" wire:click="requestChanges">Request changes</flux:button>
                    <flux:button variant="primary" wire:click="approve" class="!bg-teal-700 hover:!bg-teal-800">Looks good — approve</flux:button>
                </div>
            @endif
        </div>
    </header>

    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 lg:grid-cols-[1fr_320px] sm:px-6">
        <section class="space-y-4">
            @if ($review->context)
                <flux:callout>
                    <strong class="font-medium">What to look at:</strong> {{ $review->context }}
                </flux:callout>
            @endif

            @if ($review->screenshots->count() > 1)
                <div class="flex flex-wrap gap-2">
                    @foreach ($review->screenshots as $index => $shot)
                        <flux:button
                            size="sm"
                            variant="{{ $activeScreenshotIndex === $index ? 'primary' : 'ghost' }}"
                            wire:click="selectScreenshot({{ $index }})"
                            class="{{ $activeScreenshotIndex === $index ? '!bg-teal-700' : '' }}"
                        >
                            Shot {{ $index + 1 }}
                        </flux:button>
                    @endforeach
                </div>
            @endif

            @php($shot = $this->activeScreenshot)

            @if ($shot)
                <div
                    class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-sm dark:border-zinc-800 dark:bg-zinc-900"
                    x-data="{
                        place(e) {
                            const rect = e.currentTarget.getBoundingClientRect();
                            const x = (e.clientX - rect.left) / rect.width;
                            const y = (e.clientY - rect.top) / rect.height;
                            $wire.startPin(x, y);
                        }
                    }"
                >
                    <img
                        src="{{ $shot->url() }}"
                        alt="Screenshot {{ $activeScreenshotIndex + 1 }}"
                        class="block max-h-[75vh] w-full object-contain {{ $review->isOpenForFeedback() ? 'cursor-crosshair' : '' }}"
                        @if ($review->isOpenForFeedback())
                            x-on:click="place($event)"
                        @endif
                    />

                    @foreach ($shot->annotations as $annotation)
                        <button
                            type="button"
                            class="absolute z-10 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full text-xs font-semibold text-white shadow-lg ring-2 ring-white
                                {{ $annotation->severity === 'nit' ? 'bg-amber-500' : 'bg-teal-700' }}"
                            style="left: {{ $annotation->x * 100 }}%; top: {{ $annotation->y * 100 }}%;"
                            title="{{ $annotation->body }}"
                        >
                            {{ $annotation->number }}
                        </button>
                    @endforeach

                    @if ($pendingX !== null && $pendingY !== null)
                        <div
                            class="absolute z-20 flex h-7 w-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-rose-500 text-xs font-semibold text-white shadow-lg ring-2 ring-white"
                            style="left: {{ $pendingX * 100 }}%; top: {{ $pendingY * 100 }}%;"
                        >
                            +
                        </div>
                    @endif
                </div>

                @if ($review->isOpenForFeedback())
                    <p class="text-sm text-zinc-500">Click the screenshot to pin feedback.</p>
                @endif
            @else
                <flux:callout variant="warning">No screenshots on this review yet.</flux:callout>
            @endif

            @if ($pendingX !== null)
                <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="sm" class="mb-3">Leave a note on this spot</flux:heading>
                    <div class="space-y-3">
                        <flux:textarea wire:model="draftBody" rows="3" placeholder="Be specific — what feels off, and what would be better?" />
                        <div class="flex flex-wrap gap-3">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" wire:model="draftSeverity" value="must-fix" class="accent-teal-700">
                                Must fix
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" wire:model="draftSeverity" value="nit" class="accent-amber-500">
                                Nit
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <flux:button variant="primary" wire:click="savePin" class="!bg-teal-700">Pin feedback</flux:button>
                            <flux:button variant="ghost" wire:click="cancelPin">Cancel</flux:button>
                        </div>
                    </div>
                </div>
            @endif

            @if ($review->isOpenForFeedback())
                <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <flux:heading size="sm" class="mb-3">Overall note (optional)</flux:heading>
                    <flux:textarea wire:model="decisionNote" rows="2" placeholder="Anything else before you approve or request changes?" />
                </div>
            @elseif ($review->decision_note)
                <flux:callout>
                    <strong class="font-medium">Note to the agent:</strong> {{ $review->decision_note }}
                </flux:callout>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <flux:heading size="sm" class="mb-3">Pins</flux:heading>

                @php($pins = $shot?->annotations ?? collect())

                @if ($pins->isEmpty())
                    <p class="text-sm text-zinc-500">No pins yet. Click the screenshot to leave the first note.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($pins as $pin)
                            <li class="rounded-xl border border-zinc-100 p-3 dark:border-zinc-800">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold text-white {{ $pin->severity === 'nit' ? 'bg-amber-500' : 'bg-teal-700' }}">{{ $pin->number }}</span>
                                        <span class="text-xs uppercase tracking-wide text-zinc-500">{{ $pin->severity === 'nit' ? 'Nit' : 'Must fix' }}</span>
                                    </div>
                                    @if ($review->isOpenForFeedback())
                                        <button type="button" class="text-xs text-zinc-400 hover:text-rose-600" wire:click="deletePin({{ $pin->id }})">Remove</button>
                                    @endif
                                </div>
                                <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $pin->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</div>
