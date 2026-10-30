/**
 * The picker inside the Live view: the page's code as captured, framed on
 * the review (App\Support\LiveSnapshot). Hover outlines an element. A click
 * on words opens them for editing straight away (Done, Mark, Cancel); a
 * click on anything else selects it, named by kind, with Mark, Edit (when it
 * has words) and Wider (the part around it), each with a key: M, E, W. Either way it goes to the
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
    // Opened on its own there's no review to hear a pick, so the bar says so
    // instead of quietly closing.
    const framed = parent !== window;

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
            .hover { outline: calc(2px * var(--k)) solid rgba(59, 130, 246, .9); background: rgba(59, 130, 246, .08); }
            .picked { outline: calc(2px * var(--k)) solid #f5b400; background: rgba(245, 180, 0, .1); transition: left .18s var(--ease), top .18s var(--ease), width .18s var(--ease), height .18s var(--ease); }
            :host, .bar, .picked { --ease: cubic-bezier(.2, .8, .2, 1); }
            /* --k undoes the review's scale-to-fit, so the bar reads at full size. */
            :host { --k: 1; }
            .bar { position: absolute; display: flex; align-items: center; gap: 2px; padding: 3px; border-radius: 999px; background: #18181b; color: #fff;
                box-shadow: 0 8px 24px -6px rgba(0,0,0,.35), 0 0 0 1px rgba(255,255,255,.06) inset; font: 500 12px/1 system-ui, -apple-system, sans-serif; white-space: nowrap;
                opacity: 0; transform: translateY(4px) scale(calc(var(--k) * .96)); transform-origin: left bottom;
                transition: left .18s var(--ease), top .18s var(--ease), opacity .14s ease, transform .18s var(--ease); }
            .bar.in { opacity: 1; transform: scale(var(--k)); }
            .bar.below { transform-origin: left top; }
            .note { display: inline-flex; align-items: center; gap: 6px; padding: 0 12px 0 9px; height: 26px; }
            .note.ok svg { color: #f5b400; }
            .kind { padding: 0 8px 0 9px; color: rgba(255,255,255,.55); font-size: 11px; letter-spacing: .01em; }
            .bar button { all: unset; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; height: 26px; padding: 0 10px 0 8px; border-radius: 999px; color: #fff; transition: background .12s ease; }
            .bar button:hover, .bar button:focus-visible { background: rgba(255,255,255,.12); }
            .bar button.primary { background: #f5b400; color: #18181b; }
            .bar button.primary:hover { background: #ffc61a; }
            .bar svg { width: 14px; height: 14px; flex: none; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
            .bar kbd { font: inherit; font-size: 10px; opacity: .45; margin-left: 1px; }
            .bar button.primary kbd { opacity: .55; }
            .mark { position: absolute; display: flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 5px; box-sizing: border-box;
                border-radius: 999px; background: #f5b400; color: #18181b; font: 600 11px/1 system-ui, -apple-system, sans-serif; cursor: pointer;
                box-shadow: 0 2px 8px rgba(0,0,0,.3), 0 0 0 2px #fff; transform: translate(-40%, -40%) scale(var(--k)); transform-origin: center; }
            .mark.done { background: #d4d4d8; }
            .mark-box { position: absolute; pointer-events: none; border-radius: 3px; outline: calc(2px * var(--k)) dashed rgba(245, 180, 0, .85); }
            [hidden] { display: none !important; }
        </style>
        <div class="box hover" hidden></div>
        <div class="box picked" hidden></div>
        <div class="bar" hidden></div>
        <div class="marks"></div>`;
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

    const ICONS = {
        mark: '<path d="M3.5 4.5a1.5 1.5 0 0 1 1.5-1.5h6a1.5 1.5 0 0 1 1.5 1.5v4.5a1.5 1.5 0 0 1-1.5 1.5H7.5L5 12.5v-2a1.5 1.5 0 0 1-1.5-1.5z"/>',
        edit: '<path d="M10.5 3.5l2 2-6.75 6.75H3.75v-2z"/><path d="M9 5l2 2"/>',
        wider: '<path d="M3 6.5V3h3.5M13 6.5V3H9.5M3 9.5V13h3.5M13 9.5V13H9.5"/>',
        done: '<path d="M3.5 8.5l3 3 6-7"/>',
        cancel: '<path d="M4.5 4.5l7 7M11.5 4.5l-7 7"/>',
    };
    const KINDS = { h1: 'Heading', h2: 'Heading', h3: 'Heading', h4: 'Heading', h5: 'Heading', h6: 'Heading', p: 'Paragraph', a: 'Link', button: 'Button',
        img: 'Image', picture: 'Image', svg: 'Icon', video: 'Video', li: 'List item', ul: 'List', ol: 'List', input: 'Field', textarea: 'Field', select: 'Field',
        label: 'Label', nav: 'Navigation', header: 'Header', footer: 'Footer', form: 'Form', table: 'Table', blockquote: 'Quote', span: 'Text', strong: 'Text', em: 'Text' };
    const ROLES = { button: 'Button', img: 'Image', link: 'Link', heading: 'Heading', tab: 'Tab' };
    const kindOf = (el) => ROLES[el.getAttribute('role')] || KINDS[el.tagName.toLowerCase()] || 'Box';

    let actions = [];
    // Follows the picked element: above it when there's room, below when not,
    // gliding from one pick to the next.
    // The review scales the page down to fit; k is the inverse, so the bar
    // and outlines stay at their real size on screen.
    let k = 1;
    const setScale = (scale) => {
        k = Math.min(3, Math.max(1, 1 / (Number(scale) || 1)));
        host.style.setProperty('--k', String(k));
        placeBar();
    };
    const placeBar = () => {
        if (!picked || bar.hidden) return;
        const b = boxOf(picked);
        const gap = 8 * k;
        // The bar scales from its bottom-left corner above, its top-left below.
        const above = b.y - gap - bar.offsetHeight * k >= scrollY + 4;
        bar.classList.toggle('below', !above);
        const top = above ? b.y - gap - bar.offsetHeight : b.y + b.h + gap;
        const left = Math.min(Math.max(4, b.x), scrollX + innerWidth - bar.offsetWidth * k - 4);
        Object.assign(bar.style, { left: left + 'px', top: top + 'px' });
    };
    // Each action: [icon, one-word label, run, primary?, key].
    const showBar = (list) => {
        clearTimeout(noteTimer);
        actions = list;
        const kind = document.createElement('span');
        kind.className = 'kind';
        kind.textContent = editing ? 'Editing' : kindOf(picked);
        bar.replaceChildren(kind, ...list.map(([icon, label, run, primary, key]) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.title = key ? label + ' (' + key.toUpperCase() + ')' : label;
            button.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true">' + ICONS[icon] + '</svg>';
            button.append(label);
            if (key) button.insertAdjacentHTML('beforeend', '<kbd>' + key.toUpperCase() + '</kbd>');
            if (primary) button.className = 'primary';
            button.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); run(); });
            return button;
        }));
        const appearing = bar.hidden;
        bar.hidden = false;
        if (appearing) bar.classList.remove('in');
        placeBar();
        requestAnimationFrame(() => bar.classList.add('in'));
    };
    // What a selected (not editing) element offers: Mark always, Edit when it
    // has words, Wider when there's a part around it.
    const selectBar = () => showBar([
        ['mark', 'Mark', mark, true, 'm'],
        ...(textOf(picked) !== '' && !picked.querySelector('img, svg, video, input, select, textarea') ? [['edit', 'Edit', () => startEdit(), false, 'e']] : []),
        ...(pickable(picked.parentElement) ? [['wider', 'Wider', wider, false, 'w']] : []),
    ]);
    const wider = () => {
        if (!picked || !pickable(picked.parentElement)) return;
        picked = picked.parentElement;
        place(pickedBox, picked);
        selectBar();
    };

    const clear = () => {
        clearTimeout(noteTimer);
        if (editing) cancelEdit();
        picked = null;
        pickedBox.hidden = true;
        bar.hidden = true;
        bar.classList.remove('in');
    };

    // Says what happened in the bar for a moment, then lets go.
    let noteTimer = null;
    const note = (text, ok) => {
        const span = document.createElement('span');
        span.className = 'note' + (ok ? ' ok' : '');
        span.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true">' + (ok ? ICONS.done : ICONS.mark) + '</svg>';
        span.append(text);
        actions = [];
        bar.replaceChildren(span);
        placeBar();
        clearTimeout(noteTimer);
        noteTimer = setTimeout(clear, ok ? 900 : 2600);
    };
    // A pick or an edit goes to the review, which makes the mark.
    const deliver = (type, data) => {
        if (!framed) return note('Open Live from a review to mark this', false);
        send(type, data);
        note(type === 'edit' ? 'Marked with your words' : 'Marked', true);
    };

    const mark = () => {
        if (!picked) return;
        deliver('pick', { selector: selectorFor(picked), box: boxOf(picked), text: textOf(picked) });
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
        showBar([['done', 'Done', () => finishEdit(true), true, null], ['mark', 'Mark', markInstead, false, null], ['cancel', 'Cancel', cancelEdit, false, null]]);
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
            return deliver('edit', { selector: selectorFor(el), box: boxOf(el), text: before, after });
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
        if (picked) selectBar();
    };

    // What a pointer is on, looking past empty overlays: a link laid over a
    // whole card with nothing in it (aria-hidden, no words) would otherwise
    // take every click, as the screenshot's smallest-element pick never does.
    const overlay = (el) => el.getAttribute('aria-hidden') === 'true' && textOf(el) === '' && !el.querySelector('img, svg, video, picture, canvas');
    const targetAt = (e) => {
        if (!overlay(e.target)) return e.target;
        return document.elementsFromPoint(e.clientX, e.clientY).find((el) => !ours(el) && pickable(el) && !overlay(el)) || e.target;
    };

    document.addEventListener('mousemove', (e) => {
        const target = targetAt(e);
        if (editing || ours(e.target) || !pickable(target)) {
            hoverBox.hidden = true;
            return;
        }
        place(hoverBox, target);
    }, true);

    // Nothing on the page acts on a click: links don't go, buttons don't press.
    document.addEventListener('click', (e) => {
        if (ours(e.target)) return;
        if (editing && editing.el.contains(e.target)) return;
        e.preventDefault();
        e.stopPropagation();
        if (editing) finishEdit();
        const target = targetAt(e);
        if (!pickable(target)) return clear();
        picked = target;
        hoverBox.hidden = true;
        place(pickedBox, picked);
        if (holdsWords(picked)) {
            const caret = caretAt(e.clientX, e.clientY);
            const end = document.createRange();
            end.selectNodeContents(picked);
            end.collapse(false);
            return startEdit(caret && picked.contains(caret.startContainer) ? caret : end);
        }
        selectBar();
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
        } else if (picked && !editing && !e.metaKey && !e.ctrlKey && !e.altKey) {
            const action = actions.find((a) => a[4] === e.key.toLowerCase());
            if (action) {
                e.preventDefault();
                action[2]();
            }
        }
    }, true);

    // The review's marks, numbered on their elements (by the selector the
    // mark keeps), or where they sit on the shot when the element isn't here.
    const marksLayer = root.querySelector('.marks');
    let marks = [];
    const drawMarks = () => {
        const width = document.documentElement.scrollWidth;
        const height = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
        marksLayer.replaceChildren(...marks.flatMap((m) => {
            let el = null;
            try {
                el = m.s ? document.querySelector(m.s) : null;
            } catch (e) {}
            const b = el ? boxOf(el) : null;
            const badge = document.createElement('button');
            badge.type = 'button';
            badge.className = 'mark' + (m.done ? ' done' : '');
            badge.textContent = String(m.n);
            badge.title = 'Mark ' + m.n;
            // Kept inside the page, so a mark on something at its edge still shows whole.
            const edge = 14 * k;
            const x = Math.min(Math.max(b ? b.x : m.x * width, edge), width - edge);
            const y = Math.min(Math.max(b ? b.y : m.y * height, edge), height - edge);
            Object.assign(badge.style, { left: x + 'px', top: y + 'px' });
            badge.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); send('open-mark', { id: m.id }); });
            if (!b) return [badge];
            const outline = document.createElement('div');
            outline.className = 'mark-box';
            Object.assign(outline.style, { left: b.x + 'px', top: b.y + 'px', width: b.w + 'px', height: b.h + 'px' });
            return [outline, badge];
        }));
    };

    addEventListener('message', (e) => {
        if (e.source !== parent || !e.data || e.data.source !== 'rm-review') return;
        if (e.data.type === 'marks' && Array.isArray(e.data.marks)) {
            marks = e.data.marks;
            drawMarks();
        }
        if (e.data.type === 'clear') clear();
        if (e.data.type === 'scale') setScale(e.data.value);
    });

    // Keep the picked outline and its bar on the element as the page moves.
    const follow = () => {
        if (!picked) return;
        place(pickedBox, picked);
        placeBar();
    };
    addEventListener('scroll', follow, { passive: true });
    addEventListener('resize', follow);

    new ResizeObserver(() => marks.length && drawMarks()).observe(document.documentElement);

    const size = () => send('size', {
        width: document.documentElement.scrollWidth,
        height: Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0),
    });
    addEventListener('load', size);
    new ResizeObserver(size).observe(document.documentElement);
    size();
})();
