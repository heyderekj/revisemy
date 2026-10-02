{{-- Before and after a fix. $pin is the Alpine expression for the mark. --}}
<div class="mt-2 grid grid-cols-2 gap-2" x-show="{{ $pin }} && {{ $pin }}.after_screenshot_url">
    <figure class="overflow-hidden rounded-lg bg-well">
        <figcaption class="px-2 py-1 text-xs text-muted-foreground">Before</figcaption>
        <div class="aspect-[4/3] max-h-28 bg-cover bg-top bg-no-repeat" :style="{{ $pin }} && bgStyle({{ $pin }})"></div>
    </figure>
    <figure class="overflow-hidden rounded-lg bg-well">
        <figcaption class="px-2 py-1 text-xs text-muted-foreground">After</figcaption>
        <img class="aspect-[4/3] max-h-28 w-full object-cover object-top" :src="{{ $pin }} && {{ $pin }}.after_screenshot_url" alt="After">
    </figure>
</div>
