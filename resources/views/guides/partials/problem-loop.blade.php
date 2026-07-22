{{-- Two-column problem + How ReviseMy fits. Supports loop_steps (numbered) or loop (prose). --}}
<x-home-section>
    <div class="grid gap-10 sm:grid-cols-2 sm:gap-8 sm:items-start">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The problem</h2>
            <p class="mt-3 text-[15px] leading-relaxed text-pretty text-zinc-600">
                {{ $page['problem'] }}
            </p>
        </div>

        <div>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How ReviseMy fits</h2>

            @if (! empty($page['loop_steps']))
                <ol class="mt-4 space-y-3">
                    @foreach ($page['loop_steps'] as $index => $step)
                        <li class="flex gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-[11px] font-semibold tabular-nums text-zinc-600">
                                {{ $index + 1 }}
                            </span>
                            <p class="min-w-0 text-[15px] leading-relaxed text-zinc-600">
                                @if (! empty($step['command']))
                                    <code class="font-mono text-[13px] text-rose-600">{{ $step['command'] }}</code>{{ ! empty($step['text']) ? ' ' : '' }}
                                @endif
                                @if (! empty($step['text']))
                                    {{ $step['text'] }}
                                @endif
                                @if (! empty($step['after']))
                                    @foreach ($step['after'] as $token)
                                        @if (($token['type'] ?? '') === 'command')
                                            <code class="font-mono text-[13px] text-rose-600">{{ $token['value'] }}</code>
                                        @else
                                            {{ $token['value'] }}
                                        @endif
                                    @endforeach
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            @elseif (! empty($page['loop']))
                <p class="mt-3 text-[15px] leading-relaxed text-pretty text-zinc-600">
                    {{ $page['loop'] }}
                </p>
            @endif

            @if (! empty($page['review_type']) && ! empty($page['inputs']))
                <p class="mt-4 text-sm text-zinc-500">
                    Review type: <code class="font-mono text-[13px] text-rose-600">{{ $page['review_type'] }}</code>
                    · Exactly one ingest source per review
                </p>
            @elseif (! empty($page['review_type']))
                <p class="mt-4 text-sm text-zinc-500">
                    Review type: <code class="font-mono text-[13px] text-rose-600">{{ $page['review_type'] }}</code>
                </p>
            @endif
        </div>
    </div>
</x-home-section>
