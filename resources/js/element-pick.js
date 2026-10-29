/**
 * Finding the element under the cursor on a screenshot, from the shot's
 * element list (App\Support\ElementMap::pickList). The web review and the
 * inline review both use it, so a click picks the same element in either.
 *
 * Plain script, no imports or exports: app.js imports it for the web, and
 * App\Mcp\Resources\ReviewApp inlines it as it is, because the sandboxed
 * inline review can't load a shared chunk.
 *
 * A list is {w, h, vw, vh, kinds, e}: w and h are the page's CSS size the
 * image shows, and each row of e is [kind, x, y, w, h, parent, text, flags]
 * in CSS px. Positions in and out are shares of the image (0–1).
 */

(() => {
    // Words inside these belong to them: a click on a button's label picks the button.
    const HOLDS_TEXT = new Set(['Heading', 'Paragraph', 'Link', 'Button', 'List item', 'Label', 'Quote', 'Tab', 'Field']);

    const NOT_PICKABLE = 1;

    const loaded = new Map();

    function kindOf(list, index) {
        return list.kinds[list.e[index][0]] ?? 'Element';
    }

    /**
     * Fetch a shot's element list once per URL. Resolves to null when the shot
     * has none or it can't be read.
     */
    function load(url) {
        if (! url) {
            return Promise.resolve(null);
        }
        if (! loaded.has(url)) {
            loaded.set(url, fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((response) => (response.ok ? response.json() : null))
                .then((list) => (list && Array.isArray(list.e) ? list : null))
                .catch(() => null));
        }
        return loaded.get(url);
    }

    /**
     * The element at (nx, ny): the smallest one under it no bigger than a
     * screen, so a click on empty space in a long section picks nothing.
     */
    function at(list, nx, ny) {
        if (! list || ! list.e.length) {
            return null;
        }
        const x = nx * list.w;
        const y = ny * list.h;
        const screen = Math.max(1, list.vw * list.vh);
        let best = null;
        let bestArea = Infinity;
        for (let i = 0; i < list.e.length; i++) {
            const [, ex, ey, ew, eh, , , flags] = list.e[i];
            const area = ew * eh;
            if (flags & NOT_PICKABLE || area > screen || area >= bestArea) {
                continue;
            }
            if (x >= ex && x <= ex + ew && y >= ey && y <= ey + eh) {
                best = i;
                bestArea = area;
            }
        }
        // Loose words go to the element they're part of.
        if (best !== null && kindOf(list, best) === 'Text') {
            const parent = list.e[best][5];
            if (parent !== null && list.e[parent] && HOLDS_TEXT.has(kindOf(list, parent))) {
                best = parent;
            }
        }
        return best;
    }

    /** The element's box as shares of the image. */
    function box(list, index) {
        const [, x, y, w, h] = list.e[index];
        return { x: x / list.w, y: y / list.h, w: w / list.w, h: h / list.h };
    }

    /** Button · Get started · 151 × 52 */
    function label(list, index) {
        const row = list.e[index];
        return [kindOf(list, index), row[6], `${Math.round(row[3])} × ${Math.round(row[4])}`].filter(Boolean).join(' · ');
    }

    window.rmPick = { load, at, box, label, kind: kindOf };
})();
