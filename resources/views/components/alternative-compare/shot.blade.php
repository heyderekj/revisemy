{{-- The top of the Fieldnote sample, cropped like a screenshot. The slot sits
     over the full sample, so marks and pins use its fractions. --}}
@props(['muted' => false])

<div {{ $attributes->class(['relative aspect-[16/6.4] overflow-hidden rounded-lg ring-1 ring-black/[0.06]', 'grayscale-[0.6] opacity-80' => $muted]) }}>
    <div class="absolute inset-x-0 top-0">
        @include('components.review-mock.samples.website')
        {{ $slot }}
    </div>
</div>
