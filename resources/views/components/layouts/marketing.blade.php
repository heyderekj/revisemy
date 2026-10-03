{{-- Every secondary marketing page (guides, /for, alternatives): the site
     shell around the page, ending with a call to connect. --}}
@props([
    'title',
    'description',
    'keywords' => [],
    'fathom' => 'Connect',
])

<x-layouts.app :title="$title" :description="$description" :keywords="$keywords" schema="page">
    <x-site-shell :fathom="$fathom">
        {{ $slot }}
        @include('use-cases.partials.cta')
    </x-site-shell>
</x-layouts.app>
