<?php

namespace App\Services\Capture;

/**
 * Page function that brings a page to its final resting state before the
 * screenshot, so the capture shows what a visitor sees once everything has
 * loaded and finished animating — never a frame mid-fade.
 *
 * In order: hide the scrollbar so the page lays out at the full viewport
 * width; wait for fonts and images; optionally sweep the viewport down the
 * document so IntersectionObserver / scroll-in reveals fire; lock below-fold
 * reveals so they can't play back out when we return to the top; fast-forward
 * every CSS/WAAPI animation and transition to its end; wait until everything a
 * visitor would see has loaded (images, CSS backgrounds, autoplaying video,
 * fonts, then a quiet network); optionally hide cookie consent banners and
 * chat launchers; then wait until nothing on the page moves. It notes how
 * many images never loaded on window.__rmIncomplete, and optionally records
 * an element map (see COLLECTOR) on window.__rmElements.
 *
 * window.__rmSettleAgain() repeats only the last steps, for a caller that
 * waited on the network after the first pass (Browserless /function).
 *
 * Runs as a Puppeteer waitForFunction (Browserless and Browsershot both run
 * waitForTimeout first, so this is the last thing before the shot) or as a
 * page.evaluate inside a Browserless /function. Its own deadline stays below
 * the caller's timeout: a page that never stops moving still gets a shot.
 */
class CaptureSettleScript
{
    /**
     * Whether URL full-page captures should sweep the page first.
     */
    public static function enabled(): bool
    {
        return (bool) config('revisemy.capture.scroll_page', true);
    }

    /**
     * Max time for the scroll sweep (ms). Tall marketing pages need headroom.
     */
    public static function timeoutMs(): int
    {
        return max(5_000, (int) config('revisemy.capture.scroll_timeout_ms', 45_000));
    }

    /**
     * Pause between scroll steps (ms) so observers and CSS transitions can run.
     */
    public static function stepMs(): int
    {
        return max(50, (int) config('revisemy.capture.scroll_step_ms', 175));
    }

    /**
     * How long the page must stay perfectly still before it counts as settled.
     */
    public static function stableMs(): int
    {
        return max(100, (int) config('revisemy.capture.settle_stable_ms', 400));
    }

    /**
     * Budget for the ready gates, fast-forward and stillness wait (ms).
     */
    public static function settleTimeoutMs(): int
    {
        return max(1_000, (int) config('revisemy.capture.settle_timeout_ms', 4_000));
    }

    /**
     * Budget for the load gate: images, CSS backgrounds, video frames, fonts
     * and a quiet network (ms).
     */
    public static function loadTimeoutMs(): int
    {
        return max(1_000, (int) config('revisemy.capture.load_timeout_ms', 8_000));
    }

    /**
     * Total time the function may take; callers use it for their timeouts.
     */
    public static function budgetMs(bool $sweep, bool $collectElements = false, ?int $sweepMs = null, ?int $loadMs = null): int
    {
        // The sweep gets its own budget and restarts the settle budget after it.
        return self::settleTimeoutMs()
            + ($sweep ? ($sweepMs ?? self::timeoutMs()) + self::settleTimeoutMs() : 0)
            + ($loadMs ?? self::loadTimeoutMs())
            + ($collectElements ? self::ELEMENTS_BUDGET_MS : 0);
    }

    /**
     * Time one __rmSettleAgain() call may take.
     */
    public static function againBudgetMs(bool $collectElements = false): int
    {
        return self::settleTimeoutMs() + ($collectElements ? self::ELEMENTS_BUDGET_MS : 0);
    }

    /**
     * Headroom the outer waitForFunction / HTTP timeout gets on top of the
     * function's own deadline, so the function always resolves first.
     */
    public const OUTER_HEADROOM_MS = 3_000;

    protected const ELEMENTS_BUDGET_MS = 3_000;

    /** Most elements one map holds. */
    public const MAX_ELEMENTS = 2_500;

    /**
     * Most CSS background images the load gate waits on.
     */
    protected const MAX_BACKGROUNDS = 60;

    /**
     * Async page function (returns true once settled). It takes one optional
     * argument, a hard limit in ms that every wait inside it stops at; the
     * waitForFunction callers pass none and keep the budgets alone.
     *
     * $embedElements also writes the element map into the DOM as a JSON
     * script tag, for providers that only hand back the page's HTML.
     * $sweepBudgetMs and $loadBudgetMs replace the configured budgets when a
     * session cap leaves less time than they add up to.
     */
    public static function functionBody(
        bool $sweep = true,
        bool $hideConsent = false,
        bool $collectElements = false,
        bool $embedElements = false,
        bool $hideChat = false,
        ?int $sweepBudgetMs = null,
        ?int $loadBudgetMs = null,
    ): string {
        $config = [
            'sweep' => $sweep,
            'freeze' => (bool) config('revisemy.capture.freeze_animations', true),
            'hideConsent' => $hideConsent,
            'hideChat' => $hideChat,
            'collectElements' => $collectElements,
            'embedElements' => $embedElements,
            'placeFixed' => (bool) config('revisemy.capture.place_fixed', true),
            'stepMs' => self::stepMs(),
            'endSettleMs' => max(200, (int) config('revisemy.capture.scroll_end_settle_ms', 450)),
            'topSettleMs' => max(100, (int) config('revisemy.capture.scroll_top_settle_ms', 250)),
            'stableMs' => self::stableMs(),
            'sweepBudgetMs' => $sweep ? ($sweepBudgetMs ?? self::timeoutMs()) : 0,
            'settleBudgetMs' => self::settleTimeoutMs(),
            'loadBudgetMs' => $loadBudgetMs ?? self::loadTimeoutMs(),
            'maxBackgrounds' => self::MAX_BACKGROUNDS,
            'elementsBudgetMs' => self::ELEMENTS_BUDGET_MS,
            'maxElements' => self::MAX_ELEMENTS,
            'consentSelectors' => implode(',', self::CONSENT_SELECTORS),
            'chatSelectors' => implode(',', self::CHAT_SELECTORS),
        ];

        return str_replace(
            ['__RM_CONFIG__', '__RM_COLLECTOR__'],
            [json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), self::COLLECTOR],
            self::SCRIPT,
        );
    }

    /**
     * The element collector's source: a function of cfg, as the settle
     * script embeds it.
     */
    public static function collectorSource(): string
    {
        return self::COLLECTOR;
    }

    /**
     * The element collector as one expression that evaluates to the map,
     * for an agent to run in its own browser on the page it screenshots
     * (Playwright or Puppeteer `page.evaluate(script)`), at the top of the
     * page and right after a full-page shot.
     */
    public static function collector(int $maxElements = self::MAX_ELEMENTS): string
    {
        $config = json_encode([
            'maxElements' => max(1, min(self::MAX_ELEMENTS, $maxElements)),
            'elementsBudgetMs' => self::ELEMENTS_BUDGET_MS,
        ], JSON_THROW_ON_ERROR);

        return '('.self::COLLECTOR.')('.$config.')';
    }

    /**
     * The settle function as a self-invoking expression, for callers that hand
     * the string straight to Puppeteer's waitForFunction (Browsershot). There
     * a bare `async () => {}` is an expression that evaluates to a function —
     * truthy — so the wait resolves at once without ever running it.
     * Browserless wraps `fn` itself and takes functionBody() as-is.
     */
    public static function expression(
        bool $sweep = true,
        bool $hideConsent = false,
        bool $collectElements = false,
        bool $hideChat = false,
    ): string {
        return '('.self::functionBody($sweep, $hideConsent, $collectElements, hideChat: $hideChat).')()';
    }

    /**
     * The common consent-management platforms, plus generic cookie dialogs.
     *
     * @var list<string>
     */
    protected const CONSENT_SELECTORS = [
        '#onetrust-consent-sdk', '#onetrust-banner-sdk', '.onetrust-pc-dark-filter',
        '#CybotCookiebotDialog', '#CybotCookiebotDialogBodyUnderlay',
        '#usercentrics-root', '#usercentrics-cmp-ui',
        '#didomi-host', '.didomi-popup-backdrop',
        '.osano-cm-window', '.osano-cm-dialog',
        '#cookie-law-info-bar', '.cky-consent-container', '.cky-overlay',
        '#termly-code-snippet-support', '.termly-styles-root',
        '#qc-cmp2-container', '#truste-consent-track', '#consent_blackbar', '.truste_overlay', '.truste_box_overlay',
        '#cmplz-cookiebanner-container', '.cmplz-cookiebanner',
        '.cc-window', '.cc-banner', '#cc-main', '#cookie-notice', '#cookie-banner', '#cookiebanner',
        '[role="dialog"][aria-label*="cookie" i]', '[role="dialog"][aria-label*="consent" i]',
        '[role="alertdialog"][aria-label*="cookie" i]', '[role="region"][aria-label*="cookie" i]',
    ];

    /**
     * Chat launchers. In a full-page shot a fixed bubble lands wherever the
     * first screen put it, over whatever the page has there.
     *
     * @var list<string>
     */
    protected const CHAT_SELECTORS = [
        '#intercom-container', '.intercom-lightweight-app', '.intercom-launcher-frame', '#intercom-frame',
        '#drift-widget-container', '#drift-frame-controller', '#drift-frame-chat',
        '#hubspot-messages-iframe-container',
        '#crisp-chatbox', '.crisp-client',
        'iframe#launcher', 'iframe#webWidget', '#launcher-frame',
        'iframe[src*="tawk.to"]', '.tawk-min-container', '#tawkchat-container',
        '#tidio-chat', '#tidio-chat-iframe',
        '#chat-widget-container', '#livechat-compact-container',
        '#fc_frame', '#freshworks-container',
        '.zsiq_floatmain', '#zsiq_float',
        '#olark-wrapper', '#gorgias-chat-container', '#chatra',
    ];

    /**
     * The element map collector, on its own so a capture and an agent's own
     * browser run the same code: (cfg) => {docWidth, docHeight, viewport,
     * version, elements}. cfg needs maxElements and elementsBudgetMs.
     *
     * Each element: selector, tag, kind, text, label, role, src, box (CSS px
     * from the top of the document), parent (index of the nearest element
     * above it in the list that contains it), fixed ('top' or 'bottom' for a
     * position: fixed element or one inside it) and the styles that differ
     * from the defaults, short-keyed: fs font size, lh line height, fw
     * weight, ff first family, c colour, bg background, p padding [t, r, b, l],
     * r radius, b border [width, colour], g gap, d flex or grid. Colours are
     * hex.
     */
    protected const COLLECTOR = <<<'JS'
(cfg) => {
  const KINDS = {
    h1: 'Heading', h2: 'Heading', h3: 'Heading', h4: 'Heading', h5: 'Heading', h6: 'Heading',
    p: 'Paragraph', a: 'Link', button: 'Button', img: 'Image', picture: 'Image', svg: 'Graphic',
    video: 'Video', ul: 'List', ol: 'List', li: 'List item', nav: 'Navigation', header: 'Header',
    footer: 'Footer', section: 'Section', form: 'Form', input: 'Field', textarea: 'Field', select: 'Field',
    label: 'Label', blockquote: 'Quote', figure: 'Figure', table: 'Table',
    main: 'Main', article: 'Article', aside: 'Aside', dialog: 'Dialog',
  };
  const ROLE_KINDS = {
    button: 'Button', link: 'Link', tab: 'Tab', img: 'Image', heading: 'Heading', navigation: 'Navigation',
    dialog: 'Dialog', alertdialog: 'Dialog', checkbox: 'Field', switch: 'Field', textbox: 'Field', combobox: 'Field',
  };
  const ROLES = {
    a: 'link', button: 'button', nav: 'navigation', main: 'main', header: 'banner', footer: 'contentinfo',
    aside: 'complementary', form: 'form', img: 'img', ul: 'list', ol: 'list', li: 'listitem', table: 'table',
    dialog: 'dialog', select: 'combobox', textarea: 'textbox', h1: 'heading', h2: 'heading', h3: 'heading',
    h4: 'heading', h5: 'heading', h6: 'heading',
  };
  const TEXT_KINDS = new Set(['Heading', 'Paragraph', 'Link', 'Button', 'Text', 'Label', 'List item', 'Quote', 'Field', 'Tab']);
  const SKIP = new Set(['script', 'style', 'noscript', 'template', 'meta', 'link', 'br', 'wbr', 'source', 'track', 'path', 'g', 'defs', 'use', 'iframe']);
  const ATTRIBUTES = ['data-w-id', 'data-testid'];

  // In order of how long it stays true: an id somebody chose, a builder's own
  // element id (Webflow's data-w-id) or a test id, then the shortest unique
  // path up to the nearest ancestor with a stable handle.
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
    const parent = el.parentElement;
    if (!parent) {
      return tag;
    }
    const same = Array.from(parent.children).filter((c) => c.tagName === el.tagName);
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
  const ownText = (el) => {
    for (const child of el.childNodes) {
      if (child.nodeType === 3 && child.textContent.trim() !== '') {
        return true;
      }
    }
    return false;
  };

  // Computed colours come back as rgb(), or in the space they were written
  // in (oklch, color()). A 1x1 canvas turns any of them into sRGB bytes.
  const hexes = new Map();
  let ink = null;
  const toHex = (value) => {
    if (!value || value === 'transparent' || value === 'rgba(0, 0, 0, 0)') {
      return null;
    }
    if (hexes.has(value)) {
      return hexes.get(value);
    }
    let rgba = null;
    const m = value.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)$/);
    if (m) {
      rgba = [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]];
    } else {
      try {
        if (!ink) {
          const canvas = document.createElement('canvas');
          canvas.width = canvas.height = 1;
          ink = canvas.getContext('2d', { willReadFrequently: true });
        }
        ink.clearRect(0, 0, 1, 1);
        ink.fillStyle = '#000';
        ink.fillStyle = value;
        ink.fillRect(0, 0, 1, 1);
        const d = ink.getImageData(0, 0, 1, 1).data;
        rgba = [d[0], d[1], d[2], d[3] / 255];
      } catch (e) {}
    }
    let hex = null;
    if (rgba && rgba[3] > 0.01) {
      const byte = (n) => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0');
      hex = '#' + byte(rgba[0]) + byte(rgba[1]) + byte(rgba[2]) + (rgba[3] < 0.995 ? byte(rgba[3] * 255) : '');
    }
    hexes.set(value, hex);
    return hex;
  };
  const px = (value) => {
    const n = parseFloat(value);
    return Number.isFinite(n) ? Math.round(n * 10) / 10 : 0;
  };

  const kindOf = (el, cs, tag) => {
    const role = el.getAttribute('role');
    if (role && ROLE_KINDS[role]) {
      return ROLE_KINDS[role];
    }
    if (tag === 'a' && el.matches('.w-button, .btn, .button')) {
      return 'Button';
    }
    if (KINDS[tag]) {
      return KINDS[tag];
    }
    if (cs.backgroundImage.startsWith('url(')) {
      return 'Image';
    }
    return null;
  };
  const painted = (cs) => toHex(cs.backgroundColor) !== null
    || (px(cs.borderTopWidth) > 0 && cs.borderTopStyle !== 'none')
    || (cs.boxShadow && cs.boxShadow !== 'none');

  const stylesOf = (cs, kind) => {
    const styles = {};
    if (TEXT_KINDS.has(kind)) {
      styles.fs = px(cs.fontSize);
      if (cs.lineHeight !== 'normal') {
        styles.lh = px(cs.lineHeight);
      }
      styles.fw = parseInt(cs.fontWeight, 10) || 400;
      const family = (cs.fontFamily.split(',')[0] || '').replace(/["']/g, '').trim();
      if (family) {
        styles.ff = family.slice(0, 40);
      }
      const color = toHex(cs.color);
      if (color) {
        styles.c = color;
      }
    }
    const background = toHex(cs.backgroundColor);
    if (background) {
      styles.bg = background;
    }
    const padding = [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].map(px);
    if (padding.some((n) => n > 0)) {
      styles.p = padding;
    }
    const corners = [cs.borderTopLeftRadius, cs.borderTopRightRadius, cs.borderBottomRightRadius, cs.borderBottomLeftRadius].map(px);
    if (corners.some((n) => n > 0)) {
      styles.r = corners.every((n) => n === corners[0]) ? corners[0] : corners;
    }
    const border = px(cs.borderTopWidth);
    if (border > 0 && cs.borderTopStyle !== 'none') {
      styles.b = [border, toHex(cs.borderTopColor)];
    }
    if (/flex|grid/.test(cs.display)) {
      styles.d = cs.display;
      const gap = px(cs.rowGap) || px(cs.columnGap);
      if (gap > 0) {
        styles.g = gap;
      }
    }
    return styles;
  };

  // A position: fixed element, and everything inside it, sits where the
  // first screen put it, not where the document would.
  const fixedOf = new Map();
  const fixedFor = (el) => {
    if (!el || el === document.body || el === document.documentElement) {
      return null;
    }
    if (fixedOf.has(el)) {
      return fixedOf.get(el);
    }
    const cs = getComputedStyle(el);
    const own = el.dataset && el.dataset.rmFixed
      ? el.dataset.rmFixed
      : (cs.position === 'fixed' ? (cs.top === 'auto' && cs.bottom !== 'auto' ? 'bottom' : 'top') : null);
    const value = own || fixedFor(el.parentElement);
    fixedOf.set(el, value);
    return value;
  };

  const started = Date.now();
  const scrollX = window.scrollX;
  const scrollY = window.scrollY;
  const docWidth = Math.max(document.documentElement.scrollWidth, document.body ? document.body.scrollWidth : 0);
  const docHeight = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
  const docArea = Math.max(1, docWidth * docHeight);
  const kept = new Map();
  const elements = [];
  // Hidden by an ancestor (opacity 0, or a screen-reader-only clip), as a
  // menu's chevron button is until hover: there in layout, never seen, and
  // small enough to win every click over the link beside it.
  const clippedAway = (cs) => (cs.clip && cs.clip !== 'auto' && /rect\(\s*0/.test(cs.clip)) || /inset\(\s*50%/.test(cs.clipPath || '');
  const hiddenMemo = new Map();
  const hiddenAbove = (node) => {
    if (!node || node === document.body || node === document.documentElement) {
      return false;
    }
    if (hiddenMemo.has(node)) {
      return hiddenMemo.get(node);
    }
    const ncs = getComputedStyle(node);
    const hidden = parseFloat(ncs.opacity) === 0 || clippedAway(ncs) || hiddenAbove(node.parentElement);
    hiddenMemo.set(node, hidden);
    return hidden;
  };
  // A control with nothing on screen: no words, no fill or border, and every
  // icon inside it faded out — a menu's chevron waiting for hover.
  const showsNothing = (el, cs) => {
    if (painted(cs) || cs.backgroundImage !== 'none' || (el.innerText || '').trim() !== '') {
      return false;
    }
    // An icon font or a drawn mark in ::before / ::after still shows.
    for (const pseudo of ['::before', '::after']) {
      const pcs = getComputedStyle(el, pseudo);
      if (pcs.content && pcs.content !== 'none' && pcs.content !== 'normal' && pcs.content !== '""' && parseFloat(pcs.opacity) !== 0) {
        return false;
      }
    }
    const media = el.querySelectorAll('img, svg, video, canvas, picture');
    for (let j = 0; j < media.length && j < 12; j++) {
      const m = media[j];
      const mr = m.getBoundingClientRect();
      if (mr.width < 1 || mr.height < 1) {
        continue;
      }
      let shown = true;
      for (let node = m; node && node !== el.parentElement; node = node.parentElement) {
        const ncs = getComputedStyle(node);
        if (parseFloat(ncs.opacity) === 0 || ncs.visibility === 'hidden') {
          shown = false;
          break;
        }
      }
      if (shown) {
        return false;
      }
    }
    return true;
  };
  const nodes = document.body ? document.body.getElementsByTagName('*') : [];
  for (let i = 0; i < nodes.length && elements.length < cfg.maxElements; i++) {
    if (Date.now() - started > cfg.elementsBudgetMs) {
      break;
    }
    const el = nodes[i];
    const tag = el.tagName.toLowerCase();
    if (SKIP.has(tag) || el.closest('[data-rm-capture]')) {
      continue;
    }
    const r = el.getBoundingClientRect();
    if (r.width < 4 || r.height < 4) {
      continue;
    }
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0 || clippedAway(cs) || hiddenAbove(el.parentElement)) {
      continue;
    }
    const box = {
      x: Math.round(r.left + scrollX),
      y: Math.round(r.top + scrollY),
      w: Math.round(r.width),
      h: Math.round(r.height),
    };
    let kind = kindOf(el, cs, tag) || (ownText(el) ? 'Text' : null);
    if ((kind === 'Button' || kind === 'Link') && showsNothing(el, cs)) {
      continue;
    }
    if (!kind && painted(cs) && box.w >= 24 && box.h >= 24 && box.w * box.h < docArea * 0.6) {
      kind = 'Box';
    }
    if (!kind) {
      continue;
    }

    let parent = null;
    for (let node = el.parentElement; node && node !== document.body; node = node.parentElement) {
      if (kept.has(node)) {
        parent = kept.get(node);
        break;
      }
    }
    // A painted wrapper the same size as the element around it adds nothing.
    if (kind === 'Box' && parent !== null) {
      const outer = elements[parent].box;
      if (Math.abs(outer.x - box.x) <= 2 && Math.abs(outer.y - box.y) <= 2 && Math.abs(outer.w - box.w) <= 2 && Math.abs(outer.h - box.h) <= 2) {
        continue;
      }
    }

    const raw = el.children.length > 20 ? el.textContent : (el.innerText || el.textContent);
    const image = tag === 'img'
      ? (el.currentSrc || el.src || null)
      : ((cs.backgroundImage.match(/url\(["']?(.*?)["']?\)/) || [])[1] || null);
    const label = (el.getAttribute('aria-label') || el.getAttribute('alt') || el.getAttribute('title') || '').replace(/\s+/g, ' ').trim();
    const role = el.getAttribute('role') || (tag === 'a' && !el.hasAttribute('href') ? null : ROLES[tag]) || null;
    const fixed = fixedFor(el);
    const entry = {
      selector: selectorFor(el),
      tag,
      kind,
      text: (raw || '').replace(/\s+/g, ' ').trim().slice(0, 200),
      src: image && !image.startsWith('data:') ? image.slice(0, 500) : null,
      box,
      parent,
      styles: stylesOf(cs, kind),
    };
    if (label) {
      entry.label = label.slice(0, 120);
    }
    if (role) {
      entry.role = role;
    }
    if (fixed) {
      entry.fixed = fixed;
    }
    kept.set(el, elements.length);
    elements.push(entry);
  }
  return {
    version: 2,
    docWidth,
    docHeight,
    viewport: { width: window.innerWidth, height: window.innerHeight },
    elements,
  };
}
JS;

    protected const SCRIPT = <<<'JS'
async (hardMs) => {
  if (window.__rmSettleDone) {
    return true;
  }
  if (window.__rmSettleRunning) {
    return false;
  }
  window.__rmSettleRunning = true;
  const cfg = __RM_CONFIG__;
  // A caller with a session to fit inside sets a hard limit; every wait
  // below stops there. Without one, the budgets alone decide.
  let hardStop = Date.now() + (Number(hardMs) > 0 ? Number(hardMs) : 1e9);
  const deadlineIn = (ms) => Math.min(Date.now() + Math.max(0, ms), hardStop);
  const sleep = (ms) => new Promise((r) => setTimeout(r, Math.max(0, ms)));
  const capped = (promise, ms) => Promise.race([Promise.resolve(promise).catch(() => {}), sleep(ms)]);

  const settleImages = (ms) => capped(Promise.all(Array.from(document.images)
    .filter((img) => img.currentSrc || img.src)
    .filter((img) => !img.complete || img.naturalWidth === 0)
    .map((img) => capped(img.decode(), ms))), ms);

  const pendingImages = () => Array.from(document.images)
    .filter((img) => (img.currentSrc || img.src) && !img.complete).length;

  // Native lazy loading waits for the viewport, but the shot shows the whole
  // page: load everything now, including what the page adds later.
  const loadEagerly = () => {
    for (const el of document.querySelectorAll('img[loading="lazy"], iframe[loading="lazy"]')) {
      el.loading = 'eager';
    }
  };

  // CSS background images (Elementor and Webflow sections lean on them),
  // so the load gate can wait for those too.
  const backgroundUrls = () => {
    const urls = new Set();
    const nodes = document.body ? document.body.getElementsByTagName('*') : [];
    for (let i = 0; i < nodes.length && i < 5000 && urls.size < cfg.maxBackgrounds; i++) {
      const value = getComputedStyle(nodes[i]).backgroundImage;
      if (!value || value === 'none') {
        continue;
      }
      for (const match of value.matchAll(/url\(["']?(.*?)["']?\)/g)) {
        if (match[1] && !match[1].startsWith('data:')) {
          urls.add(match[1]);
        }
      }
    }
    return Array.from(urls);
  };

  const loadUrl = (url) => new Promise((resolve) => {
    const img = new Image();
    img.onload = img.onerror = () => resolve();
    img.src = url;
  });

  // A visitor sees an autoplaying video's frames, not a black box.
  const videoFrames = () => Promise.all(Array.from(document.querySelectorAll('video'))
    .filter((video) => (video.autoplay || !video.paused) && video.readyState < 2)
    .map((video) => new Promise((resolve) => {
      video.preload = 'auto';
      video.addEventListener('loadeddata', resolve, { once: true });
      video.addEventListener('error', resolve, { once: true });
    })));

  // Resource timing lists a request once it finishes. When none has
  // finished for quietMs, the page has stopped fetching.
  const networkQuiet = async (quietMs, maxMs) => {
    const until = deadlineIn(maxMs);
    const count = () => performance.getEntriesByType('resource').length;
    let last = count();
    let since = Date.now();
    while (Date.now() < until) {
      await sleep(100);
      const next = count();
      if (next !== last) {
        last = next;
        since = Date.now();
      } else if (Date.now() - since >= quietMs) {
        return true;
      }
    }
    return false;
  };

  // Everything a visitor would see, loaded — or the budget runs out.
  const loadGate = async (maxMs) => {
    const until = deadlineIn(maxMs);
    const left = () => Math.max(0, until - Date.now());
    loadEagerly();
    await capped(Promise.all([
      settleImages(maxMs),
      Promise.all(backgroundUrls().map(loadUrl)),
      videoFrames(),
      document.fonts ? document.fonts.ready : null,
    ]), left());
    await networkQuiet(500, left());
    await settleImages(left());
  };

  const finishAnimations = () => {
    if (!cfg.freeze || !document.getAnimations) {
      return;
    }
    for (const animation of document.getAnimations()) {
      try {
        const timing = animation.effect ? animation.effect.getComputedTiming() : null;
        if (timing && Number.isFinite(timing.endTime)) {
          animation.finish();
        } else {
          animation.pause();
        }
      } catch (e) {}
    }
  };

  const addStyle = (css) => {
    const style = document.createElement('style');
    style.setAttribute('data-rm-capture', '');
    style.textContent = css;
    (document.head || document.documentElement).appendChild(style);
  };

  // How visible an element and its first descendants are — used to tell a
  // reveal (more visible) from a reveal playing back out (less visible).
  const visibility = (el) => {
    let score = 0;
    const nodes = [el, ...Array.from(el.querySelectorAll('*')).slice(0, 20)];
    for (const node of nodes) {
      const cs = getComputedStyle(node);
      if (cs.display === 'none' || cs.visibility === 'hidden') {
        continue;
      }
      score += parseFloat(cs.opacity || '1') * (cs.transform === 'none' ? 1 : 0.98);
    }
    return score;
  };

  const setAttr = (el, name, value) => {
    if (value === null) {
      el.removeAttribute(name);
    } else {
      el.setAttribute(name, value);
    }
  };

  // Elements that change class/style while outside the viewport are reveals
  // reversing (AOS, Framer whileInView once:false) as the sweep moves past
  // them or returns to the top. Keep the version that shows more; let
  // everything else through (lazy-load swaps).
  const lockOutOfView = () => {
    const view = window.innerHeight || 800;
    const lock = new MutationObserver((records) => {
      const original = new Map();
      for (const record of records) {
        const el = record.target;
        if (!(el instanceof Element)) {
          continue;
        }
        const key = record.attributeName;
        const byEl = original.get(el) || {};
        if (!(key in byEl)) {
          byEl[key] = record.oldValue;
          original.set(el, byEl);
        }
      }
      for (const [el, attrs] of original) {
        const rect = el.getBoundingClientRect();
        if (rect.bottom > 0 && rect.top < view) {
          continue;
        }
        const current = {};
        for (const name of Object.keys(attrs)) {
          current[name] = el.getAttribute(name);
        }
        const now = visibility(el);
        for (const name of Object.keys(attrs)) {
          setAttr(el, name, attrs[name]);
        }
        if (visibility(el) <= now + 0.01) {
          for (const name of Object.keys(current)) {
            setAttr(el, name, current[name]);
          }
        }
      }
      lock.takeRecords();
    });
    lock.observe(document.documentElement, {
      subtree: true,
      attributes: true,
      attributeOldValue: true,
      attributeFilter: ['class', 'style'],
    });
    window.__rmRevealLock = lock;
  };

  const present = (selectors) => {
    try {
      return selectors !== '' && document.querySelector(selectors) !== null;
    } catch (e) {
      return false;
    }
  };

  // Consent banners cover the hero and lock the page's scroll; chat
  // launchers float over whatever the first screen had under them.
  const hideOverlays = () => {
    if (cfg.hideChat && present(cfg.chatSelectors)) {
      addStyle(cfg.chatSelectors + '{display:none!important}');
    }
    if (!cfg.hideConsent || !present(cfg.consentSelectors)) {
      return;
    }
    addStyle(cfg.consentSelectors + '{display:none!important}');
    for (const el of [document.documentElement, document.body]) {
      if (el && getComputedStyle(el).overflowY === 'hidden') {
        el.style.setProperty('overflow-y', 'visible', 'important');
      }
    }
  };

  const signature = (inViewOnly = false) => {
    const view = window.innerHeight || 800;
    const parts = [document.documentElement.scrollHeight, document.images.length];
    const nodes = document.body ? document.body.getElementsByTagName('*') : [];
    let counted = 0;
    for (let i = 0; i < nodes.length && counted < 1500; i++) {
      const node = nodes[i];
      const r = node.getBoundingClientRect();
      if ((r.width === 0 && r.height === 0) || (inViewOnly && (r.bottom <= 0 || r.top >= view))) {
        continue;
      }
      counted++;
      const cs = getComputedStyle(node);
      parts.push(Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height), cs.opacity, cs.transform);
    }
    for (const img of document.images) {
      parts.push(img.complete ? 1 : 0);
    }
    return parts.join('|');
  };

  // Wait until nothing in view moves for stableMs, or maxMs passes.
  const settleView = async (maxMs, stableMs, inViewOnly) => {
    const until = deadlineIn(Math.max(maxMs, stableMs));
    let last = signature(inViewOnly);
    let stableSince = Date.now();
    while (Date.now() < until) {
      await sleep(Math.min(100, stableMs));
      finishAnimations();
      const next = signature(inViewOnly);
      if (next !== last) {
        last = next;
        stableSince = Date.now();
      } else if (Date.now() - stableSince >= stableMs) {
        return true;
      }
    }
    return last === signature(inViewOnly);
  };

  const collectElements = () => (__RM_COLLECTOR__)(cfg);

  // How long each phase took (ms), for the capture's meta. A second pass
  // adds to its phases rather than replacing them.
  window.__rmTimings = window.__rmTimings || {};
  const timed = (key, since) => {
    window.__rmTimings[key] = (window.__rmTimings[key] || 0) + (Date.now() - since);
  };

  // A full-page shot is the whole document seen from the first screen, so
  // anything pinned to the viewport's bottom (tab bars, "Book now" bars)
  // lands over the hero, and a sticky bottom bar is pushed up over the
  // section it belongs to. Put each where a visitor last sees it: a bottom
  // bar once at the very end of the page, a sticky bottom bar back in its
  // place in the flow. Bars at the top already sit where they belong.
  // Tagged with data-rm-fixed, so the element map still knows what they are.
  const placeFixed = () => {
    if (!cfg.placeFixed || !document.body) {
      return;
    }
    window.scrollTo(0, 0);
    const view = window.innerHeight || 800;
    const docHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
    const moves = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_ELEMENT);
    let seen = 0;
    for (let el = walker.nextNode(); el && seen < 8000; el = walker.nextNode()) {
      seen++;
      if (el.dataset.rmFixed) {
        continue;
      }
      const cs = getComputedStyle(el);
      if (cs.position === 'sticky' && cs.bottom !== 'auto') {
        el.style.setProperty('position', 'relative', 'important');
        el.style.setProperty('bottom', 'auto', 'important');
        el.dataset.rmFixed = 'sticky';
        continue;
      }
      if (cs.position !== 'fixed' || cs.display === 'none' || cs.visibility === 'hidden') {
        continue;
      }
      const r = el.getBoundingClientRect();
      // Sitting low on the first screen, and a bar rather than an overlay.
      if (r.width > 0 && r.height > 0 && r.height < view * 0.5 && r.top >= view * 0.5 && r.bottom <= view + 1) {
        moves.push([el, r, view - r.top]);
      }
    }
    // Measure every one before moving any, so one move can't shift the next.
    for (const [el, r, fromBottom] of moves) {
      if (el.parentElement && el.parentElement.closest('[data-rm-fixed="bottom"]')) {
        continue;
      }
      el.style.setProperty('position', 'absolute', 'important');
      el.style.setProperty('bottom', 'auto', 'important');
      el.style.setProperty('right', 'auto', 'important');
      el.style.setProperty('width', r.width + 'px', 'important');
      el.style.setProperty('top', '0px', 'important');
      el.style.setProperty('left', '0px', 'important');
      // Where 0,0 put it says where its containing block starts.
      const origin = el.getBoundingClientRect();
      el.style.setProperty('top', (docHeight - fromBottom - origin.top) + 'px', 'important');
      el.style.setProperty('left', (r.left - origin.left) + 'px', 'important');
      el.dataset.rmFixed = 'bottom';
    }
  };

  // The last word before the shot. Consent banners and chat launchers
  // go; then wait until nothing moves for stableMs (or the budget runs out),
  // note what never loaded, and take the element map from this exact state.
  const finalPass = async (maxMs) => {
    hideOverlays();
    placeFixed();
    let since = Date.now();
    window.__rmSettleStable = await settleView(maxMs, cfg.stableMs, false);
    timed('still', since);
    window.__rmIncomplete = pendingImages();
    window.__rmLoadComplete = window.__rmIncomplete === 0;
    if (!cfg.collectElements) {
      return true;
    }
    // Scripts set some media in code (a video's source, a lazy image), which
    // the saved markup doesn't keep. Note what each one is showing, in data
    // attributes so the shot is untouched; the Live view puts them back.
    try {
      for (const media of document.querySelectorAll('video, img')) {
        const src = media.currentSrc || '';
        if (src && !src.startsWith('blob:') && !src.startsWith('data:') && media.getAttribute('src') !== src) {
          media.setAttribute('data-rm-src', src);
        }
        if (media.tagName === 'VIDEO' && media.poster) {
          media.setAttribute('data-rm-poster', media.poster);
        }
      }
    } catch (e) {}
    try {
      since = Date.now();
      window.__rmElements = collectElements();
      timed('elements', since);
      if (cfg.embedElements) {
        const old = document.getElementById('__rm-elements');
        if (old) {
          old.remove();
        }
        const tag = document.createElement('script');
        tag.type = 'application/json';
        tag.id = '__rm-elements';
        tag.textContent = JSON.stringify(window.__rmElements).replace(/</g, '\\u003c');
        (document.body || document.documentElement).appendChild(tag);
      }
    } catch (e) {
      window.__rmElements = null;
    }
    return true;
  };

  try {
    let settleDeadline = deadlineIn(cfg.settleBudgetMs);
    const left = () => Math.max(0, settleDeadline - Date.now());
    let since = Date.now();

    // 1. Lay out at the full viewport width: a headless scrollbar takes 15px
    // of it and leaves the capture narrower than the device it names. Then
    // the ready gates: fonts and every image, including lazy ones.
    addStyle('html{scrollbar-width:none!important}html::-webkit-scrollbar,body::-webkit-scrollbar{display:none!important}');
    try {
      performance.setResourceTimingBufferSize(5000);
    } catch (e) {}
    loadEagerly();
    if (document.fonts && document.fonts.ready) {
      await capped(document.fonts.ready, Math.min(2000, left()));
    }
    await settleImages(Math.min(2500, left()));
    timed('ready', since);

    // 2. Land what's already running and stop transitions from easing in,
    // so every reveal from here on arrives in its final state at once.
    finishAnimations();
    if (cfg.freeze) {
      addStyle('*,*::before,*::after{transition-duration:0s!important;transition-delay:0s!important;animation-delay:0s!important;caret-color:transparent!important}');
    }

    // 3. Sweep so scroll-triggered reveals fire (its own budget), letting
    // each screen settle the way a reader's would, and holding reveals
    // open once they've scrolled past.
    if (cfg.sweep) {
      since = Date.now();
      if (cfg.freeze) {
        lockOutOfView();
      }
      const sweepDeadline = deadlineIn(cfg.sweepBudgetMs);
      const height = Math.max(
        document.body ? document.body.scrollHeight : 0,
        document.documentElement ? document.documentElement.scrollHeight : 0,
        0
      );
      const view = Math.max(window.innerHeight || 800, 1);
      const step = Math.max(Math.floor(view * 0.85), 100);
      for (let y = 0; y < height && Date.now() < sweepDeadline; y += step) {
        window.scrollTo(0, y);
        loadEagerly();
        await sleep(cfg.stepMs);
        await settleView(Math.min(1500, Math.max(0, sweepDeadline - Date.now())), 150, true);
      }
      window.scrollTo(0, height);
      await sleep(cfg.endSettleMs);
      finishAnimations();
      window.scrollTo(0, 0);
      await sleep(cfg.topSettleMs);
      timed('sweep', since);
    }

    // 4. Load everything a visitor would see, then land what that started.
    since = Date.now();
    await loadGate(cfg.loadBudgetMs);
    timed('load', since);
    finishAnimations();
    settleDeadline = deadlineIn(cfg.settleBudgetMs);

    // 5–7. Consent banners and chat launchers out, stillness, element map.
    await finalPass(left());
    // A second pass after the caller's network wait gets its own limit.
    window.__rmSettleAgain = (ms) => {
      const limit = Number(ms) > 0 ? Math.min(Number(ms), cfg.settleBudgetMs) : cfg.settleBudgetMs;
      hardStop = Date.now() + limit;
      return finalPass(limit);
    };

    window.__rmSettleDone = true;
    return true;
  } finally {
    window.__rmSettleRunning = false;
  }
}
JS;
}
