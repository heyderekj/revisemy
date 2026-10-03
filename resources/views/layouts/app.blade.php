{{-- Livewire 4's default full-page layout ('layouts::app'). The real layout is components/layouts/app. --}}
<x-layouts.app :title="$title ?? null" :description="$description ?? null">{{ $slot }}</x-layouts.app>
