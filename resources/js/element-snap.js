// Snapping marks to page elements, shared by the web review canvas and the
// MCP inline review so both pick and walk elements the same way.
//
// Elements come from ElementAnchor::forCanvas(): {s: selector, k: kind,
// t: text, b: [x, y, w, h]} with boxes as 0–1 fractions of the capture, in
// document order. There's no DOM tree to hand, so parents are rebuilt from
// geometry: an element's parent is the smallest earlier element whose box
// holds it.

const EPSILON = 0.0005;

function contains(outer, inner) {
    return outer[0] <= inner[0] + EPSILON
        && outer[1] <= inner[1] + EPSILON
        && outer[0] + outer[2] >= inner[0] + inner[2] - EPSILON
        && outer[1] + outer[3] >= inner[1] + inner[3] - EPSILON;
}

function area(box) {
    return box[2] * box[3];
}

/**
 * @param {Array<{s: string, k: string, t: string, b: number[]}>} elements
 */
export function buildIndex(elements) {
    const list = Array.isArray(elements) ? elements : [];
    const parents = new Array(list.length).fill(-1);

    for (let i = 0; i < list.length; i++) {
        let best = -1;
        for (let j = 0; j < i; j++) {
            if (contains(list[j].b, list[i].b) && (best === -1 || area(list[j].b) <= area(list[best].b))) {
                best = j;
            }
        }
        parents[i] = best;
    }

    return { elements: list, parents };
}

/** The smallest element under a point (0–1 coords), or -1. */
export function hitTest(index, x, y) {
    let best = -1;
    index.elements.forEach((el, i) => {
        const [bx, by, bw, bh] = el.b;
        if (x >= bx && x <= bx + bw && y >= by && y <= by + bh) {
            if (best === -1 || area(el.b) <= area(index.elements[best].b)) {
                best = i;
            }
        }
    });
    return best;
}

function children(index, i) {
    const out = [];
    index.parents.forEach((p, j) => { if (p === i) out.push(j); });
    return out;
}

/**
 * Walk from one element: 'up' to its parent, 'down' to its first child,
 * 'prev' / 'next' across its siblings. Stays put at the edges.
 */
export function walk(index, i, direction) {
    if (i < 0 || i >= index.elements.length) return i;

    if (direction === 'up') {
        return index.parents[i] === -1 ? i : index.parents[i];
    }

    if (direction === 'down') {
        const kids = children(index, i);
        return kids.length ? kids[0] : i;
    }

    const siblings = children(index, index.parents[i]);
    const at = siblings.indexOf(i);
    const next = direction === 'next' ? at + 1 : at - 1;

    return siblings[next] ?? i;
}

/** How the composer and chips name an element: Heading “Pricing”. */
export function label(element) {
    if (!element) return '';
    const text = (element.t || '').trim();
    return text ? `${element.k} “${text.length > 60 ? text.slice(0, 59) + '…' : text}”` : element.k;
}

/**
 * Fan out point marks that land on the same spot so each stays clickable:
 * returns {id: offsetIndex} for marks within `threshold` of an earlier one.
 *
 * @param {Array<{id: number, x: number, y: number}>} points
 */
export function stack(points, threshold = 0.012) {
    const offsets = {};
    const placed = [];
    for (const p of points) {
        const near = placed.filter((q) => Math.abs(q.x - p.x) < threshold && Math.abs(q.y - p.y) < threshold);
        offsets[p.id] = near.length;
        placed.push(p);
    }
    return offsets;
}

export default { buildIndex, hitTest, walk, label, stack };
