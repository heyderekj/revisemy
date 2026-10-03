{{-- "Add to Cursor" / "Add to VS Code". With a token the link carries it;
     without one the editor signs in through /connect. --}}
@props(['token' => null])
@php($links = \App\Support\InstallLinks::for($token))
<div {{ $attributes->class('flex flex-wrap items-center gap-2') }}>
    <a href="{{ $links['cursor'] }}" class="btn-quiet inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-[13px] font-medium" onclick="if(window.fathom)fathom.trackEvent('Install link Cursor')">
        <x-host-icon name="cursor" /> Add to Cursor
    </a>
    <a href="{{ $links['vscode'] }}" class="btn-quiet inline-flex h-8 items-center gap-1.5 rounded-full px-3 text-[13px] font-medium" onclick="if(window.fathom)fathom.trackEvent('Install link VS Code')">
        <x-host-icon name="copilot" /> Add to VS Code
    </a>
</div>
