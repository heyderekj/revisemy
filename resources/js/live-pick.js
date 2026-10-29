/**
 * The picker inside the Live view: the page's code as captured, framed on
 * the review (App\Support\LiveSnapshot). Hover outlines an element. A click
 * on words opens them for editing straight away (Done, or Just mark it); a
 * click on anything else selects it with Mark this. Either way it goes to the
 * review as a message, and the review makes the mark.
 *
 * Plain script, no imports: LiveSnapshot inlines it into the page under the
 * response's nonce. The page runs in an opaque origin, so messages go to '*'
 * and the review checks they came from its own frame.
 *
 * Selectors follow the capture's rules (CaptureSettleScript::COLLECTOR,
 * selectorFor), so a live pick names the element the shot's map names.
 */

(() => {
    const ATTRIBUTES = ['data-w-id', 'data-testid'];
    const send = (type, data = {}) => parent.postMessage({ source: 'rm-live', type, ...data }, '*');

    const isStableId = (id) => id.length > 0 && id.length < 64 && !/\d{4,}|^[a-f0-9-]{16,}$|^(ember|react|radix|headlessui|:r)/i.test(id);
    const unique = (selector, el) => {
        try {
            const found = document.querySelectorAll(selector);
            return found.length === 1 && found[0] === el;
        } catch (e) {
            return false;
        }
    };
    const segment = (el) => {
        const tag = el.tagName.toLowerCase();
        const parentEl = el.parentElement;
        if (!parentEl) {
            return tag;
        }
        const same = Array.from(parentEl.children).filter((c) => c.tagName === el.tagName);
        return same.length === 1 ? tag : tag + ':nth-of-type(' + (same.indexOf(el) + 1) + ')';
    };
    const handleOf = (node) => {
        if (node.id && isStableId(node.id)) {
            return '#' + CSS.escape(node.id);
        }
        for (const attr of ATTRIBUTES) {
            const value = node.getAttribute(attr);
            if (value) {
                return '[' + attr + '="' + CSS.escape(value) + '"]';
            }
        }
        return null;
    };
    const selectorFor = (el) => {
        const own = handleOf(el);
        if (own && unique(own, el)) {
            return own;
        }
        const parts = [];
        let node = el;
        while (node && node !== document.documentElement) {
            const anchor = node !== el ? handleOf(node) : null;
            if (anchor && unique(anchor, node)) {
                parts.unshift(anchor);
                break;
            }
            parts.unshift(segment(node));
            const candidate = parts.join(' > ');
            if (unique(candidate, el)) {
                return candidate;
            }
            node = node.parentElement;
        }
        return parts.join(' > ');
    };

    const boxOf = (el) => {
        const r = el.getBoundingClientRect();
        return { x: r.left + scrollX, y: r.top + scrollY, w: r.width, h: r.height };
    };
    const textOf = (el) => (el.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 200);

    // Words you can change in place: the element has its own text and no
    // block-level parts inside it (a heading, a link, a button, a paragraph).
    const holdsWords = (el) => {
        if (!Array.from(el.childNodes).some((n) => n.nodeType === 3 && n.textContent.trim() !== '')) return false;
        return !Array.from(el.querySelectorAll('*')).some((child) => !/^(inline|inline-block|contents|none)$/.test(getComputedStyle(child).display));
    };
    const caretAt = (x, y) => {
        if (document.caretPositionFromPoint) {
            const p = document.caretPositionFromPoint(x, y);
            if (!p) return null;
            const range = document.createRange();
            range.setStart(p.offsetNode, p.offset);
            return range;
        }
        return document.caretRangeFromPoint ? document.caretRangeFromPoint(x, y) : null;
    };

    // Our chrome lives in a shadow root so the page's CSS can't reach it.
    const host = document.createElement('div');
    host.setAttribute('data-rm-live', '');
    host.style.cssText = 'position:absolute;inset:0 auto auto 0;width:0;height:0;z-index:2147483647;';
    const root = host.attachShadow({ mode: 'closed' });
    root.innerHTML = `
        <style>
            .box { position: absolute; pointer-events: none; border-radius: 3px; box-sizing: border-box; }
            .hover { outline: 2px solid rgba(59, 130, 246, .9); background: rgba(59, 130, 246, .08); }
            .picked { outline: 2px solid #f5b400; background: rgba(245, 180, 0, .1); }
            .bar { position: absolute; display: flex; gap: 4px; padding: 4px; border-radius: 999px; background: #18181b; box-shadow: 0 6px 20px rgba(0,0,0,.25); font: 500 12px/1 system-ui, sans-serif; }
            .bar button { all: unset; cursor: pointer; color: #fff; padding: 7px 11px; border-radius: 999px; white-space: nowrap; }
            .bar button:hover { background: rgba(255,255,255,.12); }
            .bar button.primary { background: #f5b400; color: #18181b; }
            [hidden] { display: none !important; }
        </style>
        <div class="box hover" hidden></div>
        <div class="box picked" hidden></div>
        <div class="bar" hidden></div>`;
    document.documentElement.appendChild(host);

    const [hoverBox, pickedBox, bar] = root.querySelectorAll('.hover, .picked, .bar');
    let picked = null;
    let editing = null;

    const place = (node, el) => {
        const b = boxOf(el);
        Object.assign(node.style, { left: b.x + 'px', top: b.y + 'px', width: b.w + 'px', height: b.h + 'px' });
        node.hidden = false;
    };
    const ours = (target) => target === host || host.contains(target);
    const pickable = (el) => el && el !== document.documentElement && el !== document.body && !ours(el);

    const showBar = (buttons) => {
        bar.replaceChildren(...buttons.map(([label, run, primary]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            if (primary) button.className = 'primary';
            button.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); run(); });
            return button;
        }));
        const b = boxOf(picked);
        bar.hidden = false;
        const top = b.y - bar.offsetHeight - 6;
        Object.assign(bar.style, { left: Math.max(4, b.x) + 'px', top: (top < scrollY ? b.y + b.h + 6 : top) + 'px' });
    };

    const clear = () => {
        if (editing) cancelEdit();
        picked = null;
        pickedBox.hidden = true;
        bar.hidden = true;
    };

    const mark = () => {
        if (!picked) return;
        send('pick', { selector: selectorFor(picked), box: boxOf(picked), text: textOf(picked) });
        clear();
    };

    // From a click on words (caret where you clicked), or from Edit words
    // (everything selected, ready to retype).
    const startEdit = (caret = null) => {
        if (!picked) return;
        editing = { el: picked, html: picked.innerHTML, before: textOf(picked), direct: caret !== null };
        picked.setAttribute('contenteditable', 'plaintext-only');
        picked.focus();
        const range = caret || document.createRange();
        if (!caret) range.selectNodeContents(picked);
        getSelection().removeAllRanges();
        getSelection().addRange(range);
        showBar([['Done', () => finishEdit(true), true], ['Just mark it', markInstead], ['Cancel', cancelEdit]]);
    };
    const markInstead = () => {
        editing.el.innerHTML = editing.html;
        stopEditing();
        editing = null;
        mark();
    };
    const stopEditing = () => {
        editing.el.removeAttribute('contenteditable');
        getSelection().removeAllRanges();
    };
    // Done (or Enter) with the words unchanged still marks the element;
    // clicking away with them unchanged lets it go.
    const finishEdit = (marking = false) => {
        const { el, before } = editing;
        const after = (el.innerText || '').trim();
        stopEditing();
        editing = null;
        if (after !== '' && after !== before) {
            send('edit', { selector: selectorFor(el), box: boxOf(el), text: before, after });
        } else if (marking) {
            return mark();
        }
        clear();
    };
    const cancelEdit = () => {
        const { direct } = editing;
        editing.el.innerHTML = editing.html;
        stopEditing();
        editing = null;
        if (direct) return clear();
        if (picked) showBar([['Mark this', mark, true], ['Edit words', () => startEdit()]]);
    };

    document.addEventListener('mousemove', (e) => {
        if (editing || ours(e.target) || !pickable(e.target)) {
            hoverBox.hidden = true;
            return;
        }
        place(hoverBox, e.target);
    }, true);

    // Nothing on the page acts on a click: links don't go, buttons don't press.
    document.addEventListener('click', (e) => {
        if (ours(e.target)) return;
        if (editing && editing.el.contains(e.target)) return;
        e.preventDefault();
        e.stopPropagation();
        if (editing) finishEdit();
        if (!pickable(e.target)) return clear();
        picked = e.target;
        hoverBox.hidden = true;
        place(pickedBox, picked);
        if (holdsWords(picked)) {
            const caret = caretAt(e.clientX, e.clientY);
            const end = document.createRange();
            end.selectNodeContents(picked);
            end.collapse(false);
            return startEdit(caret && picked.contains(caret.startContainer) ? caret : end);
        }
        showBar([['Mark this', mark, true], ['Edit words', () => startEdit()]]);
    }, true);

    document.addEventListener('submit', (e) => e.preventDefault(), true);
    document.addEventListener('auxclick', (e) => e.preventDefault(), true);

    document.addEventListener('keydown', (e) => {
        if (editing && e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            finishEdit(true);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            editing ? cancelEdit() : clear();
        }
    }, true);

    addEventListener('message', (e) => {
        if (e.source === parent && e.data && e.data.source === 'rm-review' && e.data.type === 'clear') clear();
    });

    const size = () => send('size', {
        width: document.documentElement.scrollWidth,
        height: Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0),
    });
    addEventListener('load', size);
    new ResizeObserver(size).observe(document.documentElement);
    size();
})();
