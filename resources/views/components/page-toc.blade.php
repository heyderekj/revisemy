{{-- The current page's sections, listed under its link in the site nav. It
     reads the h2s in the page column (skipping "Keep going" and the closing call to action),
     gives each an id if it has none, and marks the one you're reading. --}}
@props(['drawer' => false])

<ul
    x-data="{
        items: [],
        active: null,
        init() {
            const slug = (text) => text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            const headings = [...document.querySelectorAll('main#top h2')].filter((h) => ! h.closest('[data-no-toc], [data-rm-end-cta]') && h.offsetParent !== null);
            this.items = headings.map((h) => {
                h.id ||= slug(h.textContent);
                h.style.scrollMarginTop = '2rem';
                return { id: h.id, label: h.textContent.trim(), el: h };
            });
            const spy = () => {
                const passed = this.items.filter((item) => item.el.getBoundingClientRect().top < 140);
                this.active = (passed.at(-1) ?? this.items[0])?.id ?? null;
            };
            spy();
            window.addEventListener('scroll', () => requestAnimationFrame(spy), { passive: true });
        },
    }"
    x-show="items.length > 1"
    x-cloak
    {{ $attributes->class('mt-2 space-y-1.5 border-l border-border pl-3 text-[13px]') }}
>
    <template x-for="item in items" x-bind:key="item.id">
        <li>
            <a
                x-bind:href="'#' + item.id"
                x-text="item.label"
                x-bind:aria-current="active === item.id ? 'location' : null"
                x-bind:class="active === item.id ? 'text-zinc-900' : 'text-zinc-500 hover:text-zinc-900'"
                class="block py-0.5 transition-colors"
                @if ($drawer) x-on:click="mobileNav = false" @endif
            ></a>
        </li>
    </template>
</ul>
