import type React from 'react';
import { loadFont as loadCaveat } from '@remotion/google-fonts/Caveat';
import { loadFont as loadMono } from '@remotion/google-fonts/JetBrainsMono';
import { continueRender, delayRender, Easing, staticFile } from 'remotion';

// Tokens from revisemy-oss/resources/css/tokens.css, light and .dark.
// Components read `c.x`, which is a CSS variable; <ThemeProvider> sets the values.
const LIGHT = {
  key: '#ffc53d',
  keyHover: '#ffba18',
  keyInk: 'hsl(36 8% 8%)',
  highlight: '#ffe5a8',
  marker: '#ffc53d',
  y50: '#fffbe8',
  y100: '#fff7c2',
  y200: '#ffee9c',

  bg: 'hsl(0 0% 100%)',
  raised: 'hsl(0 0% 100%)',
  card: 'hsl(0 0% 97%)',
  well: 'hsl(0 0% 94.5%)',
  n100: 'hsl(0 0% 94.5%)',
  n150: 'hsl(0 0% 91%)',
  chip: 'hsl(0 0% 90%)',
  border: 'hsl(0 0% 86%)',
  borderStrong: 'hsl(0 0% 78%)',
  n400: 'hsl(0 0% 58%)',
  muted: 'hsl(0 0% 42%)',
  n600: 'hsl(0 0% 34%)',
  n700: 'hsl(0 0% 25%)',
  n800: 'hsl(0 0% 15%)',
  fg: 'hsl(0 0% 7%)',
  onFg: '#fff',

  attention: 'hsl(36 92% 50%)',
  attentionSoft: 'hsl(40 96% 92%)',
  attentionInk: 'hsl(28 80% 30%)',
  problem: 'hsl(4 74% 52%)',
  problemSoft: 'hsl(4 90% 95%)',
  problemInk: 'hsl(2 64% 38%)',
  done: 'hsl(148 52% 38%)',
  doneSoft: 'hsl(140 46% 92%)',
  doneInk: 'hsl(150 56% 22%)',

  sky50: 'oklch(97.7% 0.013 236.62)',
  sky100: 'oklch(95.1% 0.026 236.824)',
  sky200: 'oklch(90.1% 0.058 230.902)',
  sky400: 'oklch(74.6% 0.16 232.661)',
  sky500: 'oklch(68.5% 0.169 237.323)',
  sky700: 'oklch(50% 0.134 242.749)',
  sky800: 'oklch(44.3% 0.11 240.79)',

  // effects
  ring: 'rgba(0,0,0,0.06)',
  glass: 'rgba(255,255,255,0.93)',
  shimmer: 'rgba(255,255,255,0.7)',
  vignette0: 'rgba(247,247,247,0)',
  vignette1: 'rgba(247,247,247,0.85)',
  shadowWindow: '0 40px 100px -40px rgba(24,24,27,0.35), 0 12px 30px -18px rgba(24,24,27,0.18)',
  shadowRaised: '0 1px 2px rgba(0,0,0,0.05), 0 0 0 1px rgba(0,0,0,0.04)',
  shadowFloat: '0 18px 40px -20px rgba(24,24,27,0.35), 0 0 0 1px rgba(0,0,0,0.05)',
};

type Tokens = typeof LIGHT;

const DARK: Tokens = {
  ...LIGHT,
  highlight: 'hsl(40 60% 24%)',
  marker: 'hsl(42 70% 34%)',
  y50: 'hsl(42 50% 9%)',
  y100: 'hsl(42 60% 12%)',
  y200: 'hsl(42 65% 17%)',

  bg: 'hsl(0 0% 5%)',
  raised: 'hsl(0 0% 12.5%)',
  card: 'hsl(0 0% 9%)',
  well: 'hsl(0 0% 7.5%)',
  n100: 'hsl(0 0% 12.5%)',
  n150: 'hsl(0 0% 15%)',
  chip: 'hsl(0 0% 19%)',
  border: 'hsl(0 0% 18%)',
  borderStrong: 'hsl(0 0% 26%)',
  n400: 'hsl(0 0% 46%)',
  muted: 'hsl(0 0% 64%)',
  n600: 'hsl(0 0% 72%)',
  n700: 'hsl(0 0% 82%)',
  n800: 'hsl(0 0% 90%)',
  fg: 'hsl(0 0% 97%)',
  onFg: 'hsl(0 0% 5%)',

  attention: 'hsl(38 92% 56%)',
  attentionSoft: 'hsl(34 60% 15%)',
  attentionInk: 'hsl(40 96% 76%)',
  problem: 'hsl(4 80% 60%)',
  problemSoft: 'hsl(4 50% 16%)',
  problemInk: 'hsl(4 92% 80%)',
  done: 'hsl(146 50% 48%)',
  doneSoft: 'hsl(148 36% 13%)',
  doneInk: 'hsl(140 56% 74%)',

  sky50: 'oklch(22% 0.04 240)',
  sky100: 'oklch(26% 0.05 240)',
  sky200: 'oklch(32% 0.07 238)',
  sky400: 'oklch(62% 0.15 234)',
  sky500: 'oklch(68.5% 0.169 237.323)',
  sky700: 'oklch(82.8% 0.111 230.318)',
  sky800: 'oklch(90.1% 0.058 230.902)',

  ring: 'rgba(255,255,255,0.07)',
  glass: 'rgba(22,22,22,0.92)',
  shimmer: 'rgba(255,255,255,0.08)',
  vignette0: 'rgba(23,23,23,0)',
  vignette1: 'rgba(23,23,23,0.85)',
  shadowWindow: '0 40px 100px -40px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.07)',
  shadowRaised: '0 1px 2px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.05)',
  shadowFloat: '0 18px 40px -20px rgba(0,0,0,0.8), 0 0 0 1px rgba(255,255,255,0.08)',
};

export type Mode = 'light' | 'dark';

const cssName = (k: string) => `--rm-${k.replace(/[A-Z0-9]+/g, (m) => `-${m.toLowerCase()}`)}`;

export const c = Object.fromEntries(Object.keys(LIGHT).map((k) => [k, `var(${cssName(k)})`])) as Record<keyof Tokens, string>;

export const themeVars = (mode: Mode): React.CSSProperties =>
  ({
    colorScheme: mode,
    ...Object.fromEntries(Object.entries(mode === 'dark' ? DARK : LIGHT).map(([k, v]) => [cssName(k), v])),
  }) as React.CSSProperties;

export const radius = 12;

// Koati motion: decelerating only.
export const ease = {
  outStrong: Easing.bezier(0.23, 1, 0.32, 1),
  snap: Easing.bezier(0.2, 0, 0, 1),
  inOutStrong: Easing.bezier(0.77, 0, 0.175, 1),
  drawer: Easing.bezier(0.32, 0.72, 0, 1),
};

export const shadow = {
  window: c.shadowWindow,
  raised: c.shadowRaised,
  float: c.shadowFloat,
};

const caveat = loadCaveat('normal', { weights: ['500', '600', '700'], subsets: ['latin'] });
const mono = loadMono('normal', { weights: ['400', '500', '600'], subsets: ['latin'] });

export const font = {
  sans: "Figtree, ui-sans-serif, system-ui, sans-serif",
  mono: `${mono.fontFamily}, ui-monospace, SFMono-Regular, Menlo, monospace`,
  hand: `${caveat.fontFamily}, cursive`,
};

// Figtree from the app's own files.
if (typeof document !== 'undefined') {
  const handle = delayRender('Loading Figtree');
  Promise.all(
    [400, 500, 600, 700].map((w) => {
      const face = new FontFace('Figtree', `url(${staticFile(`fonts/figtree-latin-${w}.woff2`)}) format('woff2')`, {
        weight: String(w),
      });
      (document.fonts as unknown as { add: (f: FontFace) => void }).add(face);
      return face.load();
    }),
  )
    .then(() => continueRender(handle))
    .catch((err) => {
      console.error(err);
      continueRender(handle);
    });
}
