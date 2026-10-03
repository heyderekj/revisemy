<?php

namespace App\Services\Capture;

/**
 * Page function that brings a page to its final resting state before the
 * screenshot, so the capture shows what a visitor sees once everything has
 * loaded and finished animating — never a frame mid-fade.
 *
 * In order: wait for fonts and images; optionally sweep the viewport down the
 * document so IntersectionObserver / scroll-in reveals fire; lock below-fold
 * reveals so they can't play back out when we return to the top; fast-forward
 * every CSS/WAAPI animation and transition to its end; optionally hide cookie
 * consent banners; then wait until nothing on the page moves. Optionally it
 * records an element map (selector, kind, text, box) on window.__rmElements.
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
     * Total time the function may take; callers use it for their timeouts.
     */
    public static function budgetMs(bool $sweep, bool $collectElements = false): int
    {
        // The sweep gets its own budget and restarts the settle budget after it.
        return self::settleTimeoutMs()
            + ($sweep ? self::timeoutMs() + self::settleTimeoutMs() : 0)
            + ($collectElements ? self::ELEMENTS_BUDGET_MS : 0);
    }

    /**
     * Headroom the outer waitForFunction / HTTP timeout gets on top of the
     * function's own deadline, so the function always resolves first.
     */
    public const OUTER_HEADROOM_MS = 3_000;

    protected const ELEMENTS_BUDGET_MS = 3_000;

    /**
     * Async page function (returns true once settled).
     *
     * $embedElements also writes the element map into the DOM as a JSON
     * script tag, for providers that only hand back the page's HTML.
     */
    public static function functionBody(
        bool $sweep = true,
        bool $hideConsent = false,
        bool $collectElements = false,
        bool $embedElements = false,
    ): string {
        $config = [
            'sweep' => $sweep,
            'freeze' => (bool) config('revisemy.capture.freeze_animations', true),
            'hideConsent' => $hideConsent,
            'collectElements' => $collectElements,
            'embedElements' => $embedElements,
            'stepMs' => self::stepMs(),
            'endSettleMs' => max(200, (int) config('revisemy.capture.scroll_end_settle_ms', 450)),
            'topSettleMs' => max(100, (int) config('revisemy.capture.scroll_top_settle_ms', 250)),
            'stableMs' => self::stableMs(),
            'sweepBudgetMs' => $sweep ? self::timeoutMs() : 0,
            'settleBudgetMs' => self::settleTimeoutMs(),
            'elementsBudgetMs' => self::ELEMENTS_BUDGET_MS,
            'maxElements' => 2000,
            'consentSelectors' => implode(',', self::CONSENT_SELECTORS),
        ];

        return str_replace(
            '__RM_CONFIG__',
            json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            self::SCRIPT,
        );
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
    ): string {
        return '('.self::functionBody($sweep, $hideConsent, $collectElements).')()';
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

    protected const SCRIPT = <<<'JS'
async () => {
  if (window.__rmSettleDone) {
    return true;
  }
  if (window.__rmSettleRunning) {
    return false;
  }
  window.__rmSettleRunning = true;
  const cfg = __RM_CONFIG__;
  const sleep = (ms) => new Promise((r) => setTimeout(r, Math.max(0, ms)));
  const capped = (promise, ms) => Promise.race([Promise.resolve(promise).catch(() => {}), sleep(ms)]);

  const settleImages = (ms) => capped(Promise.all(Array.from(document.images)
    .filter((img) => img.currentSrc || img.src)
    .filter((img) => !img.complete || img.naturalWidth === 0)
    .map((img) => capped(img.decode(), ms))), ms);

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

  const hideConsent = () => {
    let hit = false;
    try {
      hit = document.querySelector(cfg.consentSelectors) !== null;
    } catch (e) {}
    if (!hit) {
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
    const until = Date.now() + Math.max(maxMs, stableMs);
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

  const KINDS = {
    h1: 'Heading', h2: 'Heading', h3: 'Heading', h4: 'Heading', h5: 'Heading', h6: 'Heading',
    p: 'Paragraph', a: 'Link', button: 'Button', img: 'Image', picture: 'Image', svg: 'Graphic',
    video: 'Video', ul: 'List', ol: 'List', li: 'List item', nav: 'Navigation', header: 'Header',
    footer: 'Footer', section: 'Section', form: 'Form', input: 'Field', textarea: 'Field', select: 'Field',
    label: 'Label', blockquote: 'Quote', figure: 'Figure', table: 'Table',
  };
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
  const kindOf = (el, cs) => {
    const tag = el.tagName.toLowerCase();
    if (el.getAttribute('role') === 'button' || (tag === 'a' && el.matches('.w-button, .btn, .button'))) {
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
  const ownText = (el) => {
    for (const child of el.childNodes) {
      if (child.nodeType === 3 && child.textContent.trim() !== '') {
        return true;
      }
    }
    return false;
  };

  const collectElements = () => {
    const started = Date.now();
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    const elements = [];
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
      if (cs.visibility === 'hidden' || parseFloat(cs.opacity) === 0) {
        continue;
      }
      const kind = kindOf(el, cs) || (ownText(el) ? 'Text' : null);
      if (!kind) {
        continue;
      }
      const raw = el.children.length > 20 ? el.textContent : (el.innerText || el.textContent);
      const image = tag === 'img'
        ? (el.currentSrc || el.src || null)
        : ((cs.backgroundImage.match(/url\(["']?(.*?)["']?\)/) || [])[1] || null);
      elements.push({
        selector: selectorFor(el),
        tag,
        kind,
        text: (raw || '').replace(/\s+/g, ' ').trim().slice(0, 300),
        src: image && !image.startsWith('data:') ? image.slice(0, 500) : null,
        box: {
          x: Math.round(r.left + scrollX),
          y: Math.round(r.top + scrollY),
          w: Math.round(r.width),
          h: Math.round(r.height),
        },
      });
    }
    return {
      docWidth: Math.max(document.documentElement.scrollWidth, document.body ? document.body.scrollWidth : 0),
      docHeight: Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0),
      viewport: { width: window.innerWidth, height: window.innerHeight },
      elements,
    };
  };

  try {
    let settleDeadline = Date.now() + cfg.settleBudgetMs;
    const left = () => Math.max(0, settleDeadline - Date.now());

    // 1. Ready gates: fonts and every image, including lazy ones.
    for (const img of document.querySelectorAll('img[loading="lazy"]')) {
      img.loading = 'eager';
    }
    if (document.fonts && document.fonts.ready) {
      await capped(document.fonts.ready, Math.min(2000, left()));
    }
    await settleImages(Math.min(2500, left()));

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
      if (cfg.freeze) {
        lockOutOfView();
      }
      const sweepDeadline = Date.now() + cfg.sweepBudgetMs;
      const height = Math.max(
        document.body ? document.body.scrollHeight : 0,
        document.documentElement ? document.documentElement.scrollHeight : 0,
        0
      );
      const view = Math.max(window.innerHeight || 800, 1);
      const step = Math.max(Math.floor(view * 0.85), 100);
      for (let y = 0; y < height && Date.now() < sweepDeadline; y += step) {
        window.scrollTo(0, y);
        await sleep(cfg.stepMs);
        await settleView(Math.min(1500, Math.max(0, sweepDeadline - Date.now())), 150, true);
      }
      window.scrollTo(0, height);
      await sleep(cfg.endSettleMs);
      await settleImages(1500);
      finishAnimations();
      window.scrollTo(0, 0);
      await sleep(cfg.topSettleMs);
      settleDeadline = Date.now() + cfg.settleBudgetMs;
    }

    // 4. Fast-forward anything the return to the top started.
    finishAnimations();

    // 5. Consent banners cover the hero; take them out of the shot.
    if (cfg.hideConsent) {
      hideConsent();
    }

    // 6. Wait until nothing moves for stableMs (or the budget runs out).
    window.__rmSettleStable = await settleView(left(), cfg.stableMs, false);

    // 7. Element map from the same settled page the screenshot sees.
    if (cfg.collectElements) {
      try {
        window.__rmElements = collectElements();
        if (cfg.embedElements) {
          const tag = document.createElement('script');
          tag.type = 'application/json';
          tag.id = '__rm-elements';
          tag.textContent = JSON.stringify(window.__rmElements).replace(/</g, '\\u003c');
          (document.body || document.documentElement).appendChild(tag);
        }
      } catch (e) {
        window.__rmElements = null;
      }
    }

    window.__rmSettleDone = true;
    return true;
  } finally {
    window.__rmSettleRunning = false;
  }
}
JS;
}
