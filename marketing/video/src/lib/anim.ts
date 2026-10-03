import type React from 'react';
import { interpolate, random } from 'remotion';
import { ease } from '../theme';

type Ease = (t: number) => number;

/** 0→1 between start and start+dur, clamped. */
export const prog = (frame: number, start: number, dur: number, easing: Ease = ease.outStrong) =>
  interpolate(frame, [start, start + dur], [0, 1], {
    easing,
    extrapolateLeft: 'clamp',
    extrapolateRight: 'clamp',
  });

export const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

/** Koati entrance: fade, rise a little, scale up from 0.96. */
export const appear = (frame: number, start: number, dur = 10, rise = 10): React.CSSProperties => {
  const p = prog(frame, start, dur);
  return {
    opacity: p,
    transform: `translateY(${lerp(rise, 0, p)}px) scale(${lerp(0.96, 1, p)})`,
  };
};

/** Pop for marks and badges: a short overshoot spring, still starting from 0.96-ish. */
export const popScale = (frame: number, start: number) => {
  const t = frame - start;
  if (t < 0) return 0;
  const p = Math.min(1, t / 10);
  // damped overshoot
  const s = 1 + Math.sin(p * Math.PI) * 0.12 * (1 - p);
  return interpolate(t, [0, 4], [0.6, 1], { extrapolateRight: 'clamp' }) * s;
};

/**
 * When each character of `text` appears, typed by a person: a little uneven,
 * slower after punctuation and spaces, starting at `start`.
 */
export const typingSchedule = (text: string, start: number, seed: string, speed = 1.7) => {
  const frames: number[] = [];
  let f = start;
  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    const prev = text[i - 1];
    const r = random(`${seed}-${i}`);
    // inside a word: quick and uneven
    let d = speed * (0.55 + r * 0.6);
    // the first letter of a word comes after a small beat
    if (prev === ' ') d += speed * (0.5 + random(`${seed}-w${i}`) * 0.9);
    // sentences breathe
    if (prev && ',.?!—:'.includes(prev)) d += speed * (2.2 + r * 1.5);
    // now and then, a hesitation
    if (random(`${seed}-h${i}`) < 0.05) d += speed * 2;
    f += d;
    frames.push(f);
  }
  return frames;
};

export const shownCount = (frames: number[], frame: number) => {
  let n = 0;
  while (n < frames.length && frames[n] <= frame) n++;
  return n;
};

/** Agent-style streaming: word by word, fast and even. */
export const streamSchedule = (text: string, start: number, wordsPerFrame = 0.6) => {
  const words = text.split(/(\s+)/);
  const frames: number[] = [];
  let f = start;
  let wi = 0;
  for (const w of words) {
    if (!/\s/.test(w)) wi++;
    for (let i = 0; i < w.length; i++) frames.push(start + wi / wordsPerFrame);
    f = start + wi / wordsPerFrame;
  }
  return { frames, end: f };
};
