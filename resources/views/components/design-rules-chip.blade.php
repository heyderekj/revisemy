@props([
    'review',
])

{{-- The review was checked against the project's DESIGN.md. The inline
     review shows the same chip from payload.design_rules (mcp/review-app). --}}
@if ($review->design_rules)
    @php($summary = \App\Support\DesignRules::summary(count(\App\Support\DesignRules::rules($review->design_rules)), \App\Support\DesignRules::title($review->design_rules), $review->design_rules_source))
    <span {{ $attributes->class('inline-flex shrink-0 items-center rounded-full bg-chip px-2 py-0.5 font-mono text-[11px] font-medium text-zinc-700') }} title="{{ $summary }}" aria-label="{{ $summary }}">DESIGN.md</span>
@endif
