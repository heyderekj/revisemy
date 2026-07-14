@props([
    'name',
    'size' => 'md',
])

@php
    $shell = match ($size) {
        'sm' => 'size-7 rounded-md',
        'lg' => 'size-11 rounded-xl',
        default => 'size-9 rounded-lg',
    };
    $glyph = match ($size) {
        'sm' => 'size-3.5',
        'lg' => 'size-5',
        default => 'size-[18px]',
    };
@endphp

<div {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center bg-zinc-50 text-zinc-600 ring-1 ring-zinc-200',
    $shell,
]) }}>
    @switch($name)
        @case('device-phone-mobile')
            <flux:icon.device-phone-mobile variant="micro" class="{{ $glyph }}" />
            @break
        @case('globe-alt')
            <flux:icon.globe-alt variant="micro" class="{{ $glyph }}" />
            @break
        @case('envelope')
            <flux:icon.envelope variant="micro" class="{{ $glyph }}" />
            @break
        @case('presentation-chart-bar')
            <flux:icon.presentation-chart-bar variant="micro" class="{{ $glyph }}" />
            @break
        @case('photo')
            <flux:icon.photo variant="micro" class="{{ $glyph }}" />
            @break
        @case('document')
            <flux:icon.document variant="micro" class="{{ $glyph }}" />
            @break
        @case('code-bracket')
            <flux:icon.code-bracket variant="micro" class="{{ $glyph }}" />
            @break
        @case('cursor-arrow-rays')
            <flux:icon.cursor-arrow-rays variant="micro" class="{{ $glyph }}" />
            @break
        @case('arrows-right-left')
            <flux:icon.arrows-right-left variant="micro" class="{{ $glyph }}" />
            @break
        @case('light-bulb')
            <flux:icon.light-bulb variant="micro" class="{{ $glyph }}" />
            @break
        @case('link')
            <flux:icon.link variant="micro" class="{{ $glyph }}" />
            @break
        @case('computer-desktop')
            <flux:icon.computer-desktop variant="micro" class="{{ $glyph }}" />
            @break
        @case('eye')
            <flux:icon.eye variant="micro" class="{{ $glyph }}" />
            @break
        @case('users')
            <flux:icon.users variant="micro" class="{{ $glyph }}" />
            @break
        @case('arrow-path')
            <flux:icon.arrow-path variant="micro" class="{{ $glyph }}" />
            @break
        @case('swatch')
            <flux:icon.swatch variant="micro" class="{{ $glyph }}" />
            @break
        @case('check')
            <flux:icon.check variant="micro" class="{{ $glyph }}" />
            @break
        @case('queue-list')
            <flux:icon.queue-list variant="micro" class="{{ $glyph }}" />
            @break
        @case('puzzle-piece')
            <flux:icon.puzzle-piece variant="micro" class="{{ $glyph }}" />
            @break
        @case('bolt')
            <flux:icon.bolt variant="micro" class="{{ $glyph }}" />
            @break
        @case('chatgpt')
        @case('claude')
        @case('copilot')
        @case('cursor')
        @case('grok')
            <x-host-icon :name="$name" :size="$size === 'lg' ? 'lg' : ($size === 'sm' ? 'sm' : 'md')" />
            @break
        @case('squares-2x2')
            <flux:icon.squares-2x2 variant="micro" class="{{ $glyph }}" />
            @break
        @case('paint-brush')
            <flux:icon.paint-brush variant="micro" class="{{ $glyph }}" />
            @break
        @case('clipboard-document-list')
            <flux:icon.clipboard-document-list variant="micro" class="{{ $glyph }}" />
            @break
        @case('command-line')
            <flux:icon.command-line variant="micro" class="{{ $glyph }}" />
            @break
        @case('rocket-launch')
            <flux:icon.rocket-launch variant="micro" class="{{ $glyph }}" />
            @break
        @case('building-office-2')
            <flux:icon.building-office-2 variant="micro" class="{{ $glyph }}" />
            @break
        @case('sparkles')
            <flux:icon.sparkles variant="micro" class="{{ $glyph }}" />
            @break
        @case('figma')
            {{-- Monochrome Figma mark (Simple Icons) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 24 24"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M15.852 8.981h-4.588V0h4.588c2.476 0 4.49 2.014 4.49 4.49s-2.014 4.491-4.49 4.491zM12.735 7.51h3.117c1.665 0 3.019-1.355 3.019-3.019s-1.355-3.019-3.019-3.019h-3.117V7.51zm0 1.471H8.148c-2.476 0-4.49-2.014-4.49-4.49S5.672 0 8.148 0h4.588v8.981zm-4.587-7.51c-1.665 0-3.019 1.355-3.019 3.019s1.354 3.02 3.019 3.02h3.117V1.471H8.148zm4.587 15.019H8.148c-2.476 0-4.49-2.014-4.49-4.49s2.014-4.49 4.49-4.49h4.588v8.98zM8.148 8.981c-1.665 0-3.019 1.355-3.019 3.019s1.355 3.019 3.019 3.019h3.117V8.981H8.148zM8.172 24c-2.489 0-4.515-2.014-4.515-4.49s2.014-4.49 4.49-4.49h4.588v4.441c0 2.503-2.047 4.539-4.563 4.539zm-.024-7.51a3.023 3.023 0 0 0-3.019 3.019c0 1.665 1.365 3.019 3.044 3.019 1.705 0 3.093-1.376 3.093-3.068v-2.97H8.148zm7.704 0h-.098c-2.476 0-4.49-2.014-4.49-4.49s2.014-4.49 4.49-4.49h.098c2.476 0 4.49 2.014 4.49 4.49s-2.014 4.49-4.49 4.49zm-.097-7.509c-1.665 0-3.019 1.355-3.019 3.019s1.355 3.019 3.019 3.019h.098c1.665 0 3.019-1.355 3.019-3.019s-1.355-3.019-3.019-3.019h-.098z" />
            </svg>
            @break
        @case('marker-io')
            {{-- Monochrome Marker.io mark (flame from marker.io wordmark) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 34 32"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M19.4792 21.7229C19.02 22.4187 18.3434 22.8089 17.6125 22.8089H17.6089C16.8852 22.8029 16.2089 22.4106 15.7772 21.7329C15.3416 21.0491 15.0372 20.1277 14.9419 19.2052C14.7435 17.283 14.9747 15.5211 15.8718 13.6085C16.7473 11.7416 18.1535 9.85502 18.1535 9.85502C18.1535 9.85502 21.9121 17.2631 19.4792 21.7229ZM31.8979 9.09069C30.6527 6.11751 28.8629 4.32117 25.1344 3.92521C23.3048 3.73089 20.6413 4.09176 19.1632 4.53678C17.829 2.66824 16.5695 2.19256 15.0886 1.94958C12.9 1.59035 9.95269 3.06532 8.3637 4.50088C6.56413 6.12656 5.88303 7.41451 4.7949 9.54939C3.4904 12.1088 2.63795 14.8279 2.00939 17.6233C1.26584 20.9307 0.916826 23.7864 0.949343 27.1709C0.95244 27.4929 0.902066 28.1652 1.21784 28.2669C1.65016 28.3104 4.12732 28.5836 4.46767 28.5476C4.91464 28.5137 4.88347 28.3275 4.88894 27.4016C4.91826 22.4811 5.63187 17.9624 7.53313 13.3892C8.42667 11.2402 9.45792 9.14953 11.0193 7.39208C11.5386 6.80777 12.1973 6.26667 12.902 5.94201C14.3286 5.28486 15.5947 5.62003 16.1703 6.65686C15.5465 7.22327 14.2742 8.77147 13.8265 9.45978C12.6374 11.2877 12.0504 12.9988 11.5057 14.7634C10.3377 18.5474 10.9418 22.6474 12.9137 24.5196C14.7929 26.3037 17.2729 26.4601 18.9468 26.2408C21.4278 25.915 22.7282 24.6787 23.6676 22.0416C24.8956 18.5938 24.0701 15.2587 23.1005 12.4509C22.7644 11.4775 21.0022 7.86066 21.0022 7.86066C21.0022 7.86066 21.7234 7.30371 23.2303 7.43847C23.9432 7.50225 24.6735 7.57643 25.3654 7.85305C26.7763 8.41678 27.8939 9.49466 28.4321 10.8103C29.3834 13.1365 29.37 15.6687 28.3895 18.7796C27.3887 21.9542 25.645 24.7568 23.9094 27.3125C23.7033 27.5835 23.6244 27.7946 23.6622 27.9663C23.6964 28.1214 23.8352 28.2647 24.0748 28.3917C24.424 28.6064 24.7289 28.81 25.0236 29.0071C25.4204 29.2725 25.7953 29.5231 26.2104 29.7567C26.4247 29.901 26.6213 29.9602 26.7773 29.9288C26.8895 29.9068 26.9811 29.8407 27.0574 29.7262C28.9736 26.7623 30.9074 23.5145 32.0621 19.8674C33.3223 15.888 33.2687 12.3629 31.8979 9.09069Z" />
            </svg>
            @break
        @case('pastel')
            {{-- Monochrome Pastel mark (circle + arcs from usepastel.com logo) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 22 29"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M10.9714 21.9428C17.0308 21.9428 21.9428 17.0308 21.9428 10.9714C21.9428 4.91207 17.0308 0 10.9714 0C4.91207 0 0 4.91207 0 10.9714C0 17.0308 4.91207 21.9428 10.9714 21.9428Z" />
                <path d="M0 10.0572C6.31174 10.0572 11.4285 15.1739 11.4285 21.4858V28.8001H0V10.0572Z" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M0.037454 10.0572H5.66846C8.84962 10.0572 11.4285 12.636 11.4285 15.8172V21.9336C11.2769 21.9398 11.1245 21.9429 10.9713 21.9429C4.91197 21.9429 0 17.0308 0 10.9715C0 10.6636 0.0125832 10.3587 0.037454 10.0572Z" />
            </svg>
            @break
        @case('lucidly')
            {{-- Monochrome Lucidly mark (rounded-square cluster from lucidly.so/lucidly-logo.svg) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 48 56"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <rect x="15.3809" y="51.9102" width="12.2141" height="12.2141" rx="2.18588" transform="rotate(-90 15.3809 51.9102)" />
                <rect x="20.1211" y="16.125" width="12.2141" height="12.2141" rx="2.18588" transform="rotate(-90 20.1211 16.125)" />
                <rect x="35.4238" y="15.9727" width="12.2141" height="12.2141" rx="2.18588" transform="rotate(-90 35.4238 15.9727)" />
                <rect x="15.4199" y="36.2598" width="12.2141" height="12.2141" rx="2.18588" transform="rotate(-90 15.4199 36.2598)" />
                <rect x="35.5" y="31.5059" width="12.2141" height="12.2141" rx="2.18588" transform="rotate(-90 35.5 31.5059)" />
                <rect y="36.5312" width="12.2141" height="12.2141" rx="2.18588" transform="rotate(-90 0 36.5312)" />
            </svg>
            @break
        @case('markup-io')
            {{-- Monochrome MarkUp.io mark (slash cluster from markup.io logo) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 140.1 87.3"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M129.6 86.4c-5.6 0-10.2-4.6-10.2-10.2s4.6-10.2 10.2-10.2 10.2 4.6 10.2 10.2-4.5 10.2-10.2 10.2z" />
                <path d="M121.1 62.8c-4.9 2.8-11.1 1.1-13.9-3.7l-24-42.9C80.3 11.3 82 5 86.9 2.2c4.8-2.8 11.1-1.1 13.9 3.8l24 42.9c2.8 4.8 1.1 11.1-3.7 13.9z" />
                <path d="M5.5 85.1C.6 82.3-1 76 1.8 71.2L39.4 6c2.8-4.9 9.1-6.5 13.9-3.7 4.9 2.8 6.5 9.1 3.7 13.9L19.4 81.4C16.6 86.2 10.3 87.9 5.5 85.1z" />
                <path d="M49.4 85.1c-4.9-2.8-6.5-9.1-3.7-13.9L83.2 6c2.8-4.9 9.1-6.5 13.9-3.7 4.9 2.8 6.5 9.1 3.7 13.9L63.3 81.4c-2.8 4.8-9.1 6.5-13.9 3.7z" />
                <path d="M90.9 85.1c-4.9 2.8-11.1 1.1-13.9-3.7L39.4 16.2c-2.8-4.9-1.1-11.1 3.7-13.9 4.9-2.8 11.1-1.1 13.9 3.7l37.6 65.1c2.8 4.9 1.2 11.2-3.7 14z" />
            </svg>
            @break
        @case('workflow-design')
            {{-- Monochrome Workflow mark (serif W from workflow.design favicon) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 24 24"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M3.2 6.2h3.4l1.55 8.35L12 6.2h2.9l3.85 8.35L20.3 6.2H23l-3.7 12.1h-3.55L12 9.55l-3.75 8.75H4.7L1 6.2h2.2z" />
            </svg>
            @break
        @case('simple-commenter')
            {{-- Monochrome Simple Commenter mark (speech bubble from product favicon) --}}
            <svg
                class="{{ $glyph }}"
                viewBox="0 0 24 24"
                fill="currentColor"
                aria-hidden="true"
                focusable="false"
            >
                <path d="M5 3.75A3.75 3.75 0 0 1 8.75 0h6.5A3.75 3.75 0 0 1 19 3.75v8.5A3.75 3.75 0 0 1 15.25 16H11.1l-4.35 4.05c-.55.51-1.4.12-1.4-.62v-3.55A3.75 3.75 0 0 1 5 12.25v-8.5z" />
            </svg>
            @break
        @default
            <flux:icon.photo variant="micro" class="{{ $glyph }}" />
    @endswitch
</div>
