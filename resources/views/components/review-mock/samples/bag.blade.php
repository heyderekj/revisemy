{{-- A coffee bag for the Fieldnote samples: pouch, crimped top, paper label.
     Expects $name, $notes and $bag (a fill-* class). --}}
<svg viewBox="0 0 60 80" class="h-full w-auto" aria-hidden="true">
    <path d="M11 15h38l4 60a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3Z" class="{{ $bag }}" />
    <rect x="10" y="5" width="40" height="11" rx="1.5" class="{{ $bag }}" />
    <rect x="10" y="5" width="40" height="11" rx="1.5" class="fill-black/15" />
    <path d="M14 8v5M19 8v5M24 8v5M29 8v5M34 8v5M39 8v5M44 8v5" class="stroke-black/20" stroke-width="0.8" />
    <rect x="14" y="30" width="32" height="32" rx="2" class="fill-zinc-50" />
    <circle cx="30" cy="37" r="2.4" class="fill-attention" />
    <text x="30" y="47" text-anchor="middle" class="fill-zinc-900" font-size="5.6" font-weight="600" letter-spacing="0.2">{{ $name }}</text>
    <text x="30" y="54" text-anchor="middle" class="fill-zinc-500" font-size="3.4">{{ $notes }}</text>
</svg>
