{{-- Two-column problem + fit list. Used when $page['loop_steps'] is set. --}}
<section class="mt-14 scroll-mt-8 sm:mt-16">
    <div class="grid gap-10 sm:grid-cols-2 sm:gap-8 sm:items-start">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">The problem</h2>
            <p class="mt-3 text-[15px] leading-relaxed text-pretty text-zinc-600">
                {{ $page['problem'] }}
            </p>
        </div>

        <div>
            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 sm:text-2xl">How ReviseMy fits</h2>
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
        </div>
    </div>
</section>
