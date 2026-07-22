<?php

namespace App\Services\Capture;

/**
 * Page function that walks the viewport down the document so
 * IntersectionObserver / scroll-triggered reveals fire before a full-page
 * screenshot. Browserless runs waitForTimeout before scrollPage, so a settle
 * delay alone never reveals below-the-fold animated content — the scroll must
 * live inside waitForFunction (with its own settle) instead.
 */
class ScrollRevealScript
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
     * Puppeteer/Browserless waitForFunction body (async, returns true when done).
     */
    public static function functionBody(): string
    {
        $stepMs = self::stepMs();
        // Final pauses after last section + after returning to top.
        $endSettleMs = max(200, (int) config('revisemy.capture.scroll_end_settle_ms', 450));
        $topSettleMs = max(100, (int) config('revisemy.capture.scroll_top_settle_ms', 250));

        return <<<JS
async () => {
  if (window.__rmScrollRevealDone) {
    return true;
  }
  if (window.__rmScrollRevealRunning) {
    return false;
  }
  window.__rmScrollRevealRunning = true;
  try {
    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
    const height = Math.max(
      document.body ? document.body.scrollHeight : 0,
      document.documentElement ? document.documentElement.scrollHeight : 0,
      0
    );
    const view = Math.max(window.innerHeight || 800, 1);
    const step = Math.max(Math.floor(view * 0.85), 100);
    for (let y = 0; y < height; y += step) {
      window.scrollTo(0, y);
      await sleep({$stepMs});
    }
    window.scrollTo(0, height);
    await sleep({$endSettleMs});
    window.scrollTo(0, 0);
    await sleep({$topSettleMs});
    window.__rmScrollRevealDone = true;
    return true;
  } finally {
    window.__rmScrollRevealRunning = false;
  }
}
JS;
    }
}
