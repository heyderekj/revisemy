import React from 'react';
import { AbsoluteFill, Img, interpolate, random, staticFile, useCurrentFrame } from 'remotion';
import { lerp, prog, shownCount, streamSchedule, typingSchedule } from '../lib/anim';
import { Sfx, SfxName } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';

/* ---------------------------------------------------------------- texture */

/** The homepage's .rm-dot-grid, scaled for 1080p, drifting slowly. */
export const DotGrid: React.FC<{
  reveal?: number; // 0..1, grows from the centre
  dx?: number;
  dy?: number;
  opacity?: number;
}> = ({ reveal = 1, dx = 0, dy = 0, opacity = 1 }) => {
  const frame = useCurrentFrame();
  const pitch = 22;
  const x = 11 + frame * 0.12 + dx;
  const y = 11 + frame * 0.06 + dy;
  const r = lerp(0, 1500, reveal);
  const mask = reveal >= 1 ? undefined : `radial-gradient(circle at 50% 50%, #000 ${r}px, transparent ${r + 260}px)`;
  return (
    <AbsoluteFill style={{ background: c.card }}>
      <AbsoluteFill
        style={{
          opacity,
          backgroundImage: `radial-gradient(circle at center, ${c.borderStrong} 1.4px, transparent 1.8px)`,
          backgroundSize: `${pitch}px ${pitch}px`,
          backgroundPosition: `${x}px ${y}px`,
          WebkitMaskImage: mask,
          maskImage: mask,
        }}
      />
      {/* soft vignette so the centre reads as the stage */}
      <AbsoluteFill
        style={{ background: `radial-gradient(ellipse at 50% 45%, ${c.vignette0} 40%, ${c.vignette1} 100%)` }}
      />
    </AbsoluteFill>
  );
};

/* ------------------------------------------------------------------ text */

const KEYS = Array.from({ length: 12 }, (_, i) => `key-${i + 1}`) as SfxName[];

export const typedEnd = (text: string, start: number, seed: string, speed?: number) => {
  const f = typingSchedule(text, start, seed, speed);
  return f[f.length - 1] ?? start;
};

/** Text typed by a person, with a key sound per character. */
export const Typed: React.FC<{
  text: string;
  start: number;
  seed: string;
  speed?: number;
  caret?: boolean;
  caretUntil?: number;
  volume?: number;
  enter?: boolean;
  style?: React.CSSProperties;
}> = ({ text, start, seed, speed, caret = true, caretUntil, volume = 0.3, enter = false, style }) => {
  const frame = useCurrentFrame();
  const frames = typingSchedule(text, start, seed, speed);
  const n = shownCount(frames, frame);
  const end = frames[frames.length - 1] ?? start;
  const typing = frame >= start && frame <= end + 2;
  const blinkOn = typing || Math.floor((frame - start) / 15) % 2 === 0;
  const showCaret = caret && frame >= start - 10 && (caretUntil === undefined || frame < caretUntil) && blinkOn;
  return (
    <span style={style}>
      {text.slice(0, n)}
      <Caret visible={showCaret} />
      {frames.map((f, i) => {
        // one sound per frame is plenty
        if (i > 0 && Math.floor(frames[i - 1]) === Math.floor(f)) return null;
        const ch = text[i];
        // never the same key sample twice in a row
        const pick = (Math.floor(random(`${seed}-k${i}`) * (KEYS.length - 1)) + i * 5) % KEYS.length;
        const name: SfxName = ch === ' ' ? (i % 2 ? 'space-1' : 'space-2') : KEYS[pick];
        return <Sfx key={i} at={Math.floor(f)} name={name} volume={0.62 * volume * (0.7 + random(`${seed}-v${i}`) * 0.45)} />;
      })}
      {enter ? <Sfx at={end + 9} name="enter" volume={volume * 1.2} /> : null}
    </span>
  );
};

export const Caret: React.FC<{ visible: boolean; color?: string; h?: string }> = ({ visible, color = c.fg, h = '1.05em' }) => (
  <span
    style={{
      display: 'inline-block',
      width: 2,
      height: h,
      marginLeft: 2,
      verticalAlign: '-0.15em',
      background: color,
      opacity: visible ? 1 : 0,
    }}
  />
);

/** Agent text, streamed word by word. */
export const Streamed: React.FC<{ text: string; start: number; wpf?: number; style?: React.CSSProperties }> = ({
  text,
  start,
  wpf = 0.55,
  style,
}) => {
  const frame = useCurrentFrame();
  const { frames } = streamSchedule(text, start, wpf);
  const n = shownCount(frames, frame);
  return (
    <span style={style}>
      <span>{text.slice(0, n)}</span>
      <span style={{ opacity: 0 }}>{text.slice(n)}</span>
    </span>
  );
};

/* ---------------------------------------------------------------- cursor */

export type CursorKey = { f: number; x: number; y: number };

export const cursorAt = (path: CursorKey[], frame: number) => {
  if (frame <= path[0].f) return path[0];
  for (let i = 0; i < path.length - 1; i++) {
    const a = path[i];
    const b = path[i + 1];
    if (frame <= b.f) {
      const t = ease.inOutStrong((frame - a.f) / Math.max(1, b.f - a.f));
      // a slight arc so moves feel hand-made
      const arc = Math.sin(t * Math.PI) * Math.min(40, Math.hypot(b.x - a.x, b.y - a.y) * 0.08);
      return { f: frame, x: lerp(a.x, b.x, t), y: lerp(a.y, b.y, t) - arc };
    }
  }
  return path[path.length - 1];
};

/** A pointer that glides along `path`, clicks at `clicks`, holds during `holds`. */
export const Cursor: React.FC<{
  path: CursorKey[];
  clicks?: number[];
  holds?: { from: number; to: number }[];
  appearAt?: number;
  hideAt?: number;
  volume?: number;
}> = ({ path, clicks = [], holds = [], appearAt, hideAt, volume = 0.6 }) => {
  const frame = useCurrentFrame();
  const p = cursorAt(path, frame);
  const start = appearAt ?? path[0].f;
  const fadeIn = prog(frame, start, 8);
  const fadeOut = hideAt === undefined ? 1 : 1 - prog(frame, hideAt, 8);
  const held = holds.some((h) => frame >= h.from && frame <= h.to);
  const clickDip = clicks.reduce((s, cf) => {
    const t = frame - cf;
    return t >= 0 && t < 8 ? Math.min(s, interpolate(t, [0, 2, 8], [1, 0.82, 1])) : s;
  }, 1);
  const scale = held ? 0.88 : clickDip;
  return (
    <>
      {clicks.map((cf) => (
        <Ripple key={cf} at={cf} x={p.x} y={p.y} path={path} />
      ))}
      <div
        style={{
          position: 'absolute',
          left: p.x,
          top: p.y,
          opacity: fadeIn * fadeOut,
          transform: `translate(-6px, -4px) scale(${scale})`,
          transformOrigin: '6px 4px',
          zIndex: 100,
          filter: 'drop-shadow(0 4px 6px rgba(0,0,0,0.25))',
        }}
      >
        <svg width="34" height="40" viewBox="0 0 34 40">
          <path
            d="M6 4 L6 31 L12.5 25 L17 35.5 L22 33.4 L17.6 23.4 L26.5 23.4 Z"
            fill="#fff"
            stroke={c.fg}
            strokeWidth="2.2"
            strokeLinejoin="round"
          />
        </svg>
      </div>
      {clicks.map((cf) => (
        <Sfx key={`s${cf}`} at={cf} name="click" volume={volume} />
      ))}
      {holds.map((h) => (
        <React.Fragment key={`h${h.from}`}>
          <Sfx at={h.from} name="click-down" volume={volume} />
          <Sfx at={h.to} name="click-up" volume={volume} />
        </React.Fragment>
      ))}
    </>
  );
};

const Ripple: React.FC<{ at: number; x: number; y: number; path: CursorKey[] }> = ({ at, path }) => {
  const frame = useCurrentFrame();
  const t = frame - at;
  if (t < 0 || t > 16) return null;
  const pos = cursorAt(path, at);
  const p = t / 16;
  const size = lerp(10, 70, ease.outStrong(p));
  return (
    <div
      style={{
        position: 'absolute',
        left: pos.x - size / 2,
        top: pos.y - size / 2,
        width: size,
        height: size,
        borderRadius: '50%',
        border: `3px solid ${c.key}`,
        background: 'rgba(255,197,61,0.18)',
        opacity: 1 - p,
        zIndex: 99,
      }}
    />
  );
};

/* ------------------------------------------------------------ primitives */

export const AppIcon: React.FC<{ size: number; style?: React.CSSProperties }> = ({ size, style }) => (
  <Img src={staticFile('brand/app-icon.png')} style={{ width: size, height: size, borderRadius: size * 0.22, ...style }} />
);

export const Wordmark: React.FC<{ size: number }> = ({ size }) => (
  <div style={{ display: 'flex', alignItems: 'center', gap: size * 0.32 }}>
    <AppIcon size={size} />
    <span style={{ fontFamily: font.sans, fontWeight: 600, fontSize: size * 0.72, letterSpacing: '-0.02em', color: c.fg }}>
      ReviseMy
    </span>
  </div>
);

export const Chip: React.FC<{ children: React.ReactNode; style?: React.CSSProperties }> = ({ children, style }) => (
  <span
    style={{
      display: 'inline-flex',
      alignItems: 'center',
      gap: 6,
      borderRadius: 7,
      background: c.chip,
      padding: '3px 9px',
      fontSize: 15,
      fontWeight: 500,
      color: c.n600,
      fontFamily: font.sans,
      whiteSpace: 'nowrap',
      ...style,
    }}
  >
    {children}
  </span>
);

const TONES = {
  neutral: { bg: c.chip, fg: c.n700, dot: c.n400 },
  agent: { bg: c.sky50, fg: c.sky800, dot: c.sky500 },
  attention: { bg: c.attentionSoft, fg: c.attentionInk, dot: c.attention },
  problem: { bg: c.problemSoft, fg: c.problemInk, dot: c.problem },
  done: { bg: c.doneSoft, fg: c.doneInk, dot: c.done },
};
export type Tone = keyof typeof TONES;

export const statusTone = (s: string): Tone =>
  s === 'In progress' ? 'agent' : s === 'Resolved' ? 'attention' : s === 'Verified' ? 'done' : 'neutral';

/** x-signal-tag: soft wash with a solid dot. */
export const SignalTag: React.FC<{ tone: Tone; children: React.ReactNode; scale?: number }> = ({ tone, children, scale = 1 }) => {
  const t = TONES[tone];
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 7 * scale,
        borderRadius: 7 * scale,
        background: t.bg,
        color: t.fg,
        padding: `${3 * scale}px ${8 * scale}px`,
        fontSize: 14 * scale,
        fontWeight: 500,
        fontFamily: font.sans,
        whiteSpace: 'nowrap',
      }}
    >
      <span style={{ width: 7 * scale, height: 7 * scale, borderRadius: '50%', background: t.dot }} />
      {children}
    </span>
  );
};

/** M1 / S1 / G1 badges. */
export const MarkBadge: React.FC<{
  label: string;
  kind?: 'mark' | 'hint' | 'guest';
  size?: number;
  style?: React.CSSProperties;
}> = ({ label, kind = 'mark', size = 30, style }) => {
  const base: React.CSSProperties = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: size,
    height: size,
    padding: '0 5px',
    borderRadius: size,
    fontFamily: font.sans,
    fontWeight: 600,
    fontSize: size * 0.46,
    boxSizing: 'border-box',
    flexShrink: 0,
  };
  const k: React.CSSProperties =
    kind === 'mark'
      ? { background: c.key, color: c.keyInk }
      : kind === 'hint'
        ? { background: c.bg, color: c.sky700, border: `2px dashed ${c.sky500}` }
        : { background: c.n600, color: c.onFg };
  return <span style={{ ...base, ...k, ...style }}>{label}</span>;
};

export const Button: React.FC<{
  children: React.ReactNode;
  variant?: 'primary' | 'ghost' | 'dark';
  pressed?: number; // 0..1 press amount
  style?: React.CSSProperties;
}> = ({ children, variant = 'ghost', pressed = 0, style }) => {
  const v: React.CSSProperties =
    variant === 'primary'
      ? { background: c.key, color: c.keyInk, boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.45), 0 1px 2px rgba(0,0,0,0.12)' }
      : variant === 'dark'
        ? { background: c.fg, color: c.onFg }
        : { background: c.chip, color: c.n800 };
  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 8,
        height: 38,
        padding: '0 15px',
        borderRadius: 10,
        fontFamily: font.sans,
        fontWeight: 500,
        fontSize: 16,
        whiteSpace: 'nowrap',
        transform: `scale(${1 - pressed * 0.05})`,
        ...v,
        ...style,
      }}
    >
      {children}
    </span>
  );
};

/* ------------------------------------------------------------------ icons */

export const Icon: React.FC<{ name: 'check' | 'link' | 'board' | 'undo' | 'send' | 'spark' | 'arrow' | 'tool'; size?: number; color?: string }> = ({
  name,
  size = 16,
  color = 'currentColor',
}) => {
  // stroke via currentColor so theme variables work (SVG attributes can't read var())
  const p = { fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" style={color === 'currentColor' ? undefined : { color }}>
      {name === 'check' && <path d="M5 12.5l4.5 4.5L19 7.5" {...p} strokeWidth={2.6} />}
      {name === 'link' && (
        <>
          <path d="M10 14a4 4 0 005.66 0l3-3a4 4 0 00-5.66-5.66l-1 1" {...p} />
          <path d="M14 10a4 4 0 00-5.66 0l-3 3a4 4 0 005.66 5.66l1-1" {...p} />
        </>
      )}
      {name === 'board' && (
        <>
          <rect x="3.5" y="4.5" width="5" height="15" rx="1.5" {...p} />
          <rect x="10.5" y="4.5" width="5" height="10" rx="1.5" {...p} />
          <rect x="17.5" y="4.5" width="3" height="7" rx="1.2" {...p} />
        </>
      )}
      {name === 'undo' && <path d="M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-3" {...p} />}
      {name === 'send' && <path d="M12 19V5M5.5 11.5L12 5l6.5 6.5" {...p} strokeWidth={2.4} />}
      {name === 'spark' && <path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M6 18l2.5-2.5M15.5 8.5L18 6" {...p} />}
      {name === 'arrow' && <path d="M5 12h14M13 6l6 6-6 6" {...p} />}
      {name === 'tool' && <path d="M14.7 6.3a4 4 0 00-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 005.4-5.4l-2.5 2.5-2.5-.5-.5-2.5z" {...p} />}
    </svg>
  );
};

export const Spinner: React.FC<{ size?: number; color?: string }> = ({ size = 16, color = c.n600 }) => {
  const frame = useCurrentFrame();
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" style={{ transform: `rotate(${frame * 14}deg)` }}>
      <circle cx="12" cy="12" r="9" fill="none" style={{ stroke: c.border }} strokeWidth="3" />
      <path d="M12 3a9 9 0 019 9" fill="none" style={{ stroke: color }} strokeWidth="3" strokeLinecap="round" />
    </svg>
  );
};

export { shadow };
