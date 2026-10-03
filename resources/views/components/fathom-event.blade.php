{{--
    Fires one Fathom event when the page loads, for pages (not buttons) that
    are themselves the moment: a purchase, a canceled checkout.

    `value` is in cents; Fathom reads the currency from the event's setting in
    its dashboard. `once` makes it fire once per browser tab for that key, so
    reloading a receipt page doesn't count the same purchase twice.
--}}
@props([
    'name',
    'value' => null,
    'once' => null,
])

@if (config('seo.fathom_site_id'))
    <script>
        window.addEventListener('load', function () {
            try {
                if (!window.fathom) return;
                var key = {{ \Illuminate\Support\Js::from($once ? 'rm-fathom:'.$once : null) }};
                if (key) {
                    if (sessionStorage.getItem(key)) return;
                    sessionStorage.setItem(key, '1');
                }
                fathom.trackEvent({{ \Illuminate\Support\Js::from($name) }}{!! $value === null ? '' : ', { _value: '.(int) $value.' }' !!});
            } catch (e) {}
        });
    </script>
@endif
