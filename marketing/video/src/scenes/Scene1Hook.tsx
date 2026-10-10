import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { cursorAt, Cursor, DotGrid, MarkBadge, Typed } from '../components/ui';
import { voFrames, VoLine } from '../components/Vo';
import { lerp, popScale, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font } from '../theme';

// v1 starts once the headline is boxed; the hook holds until it's said.
const HOOK_VO = 40;
export const HOOK_DURATION = Math.max(168, HOOK_VO + voFrames('v1') + 24);

// The box the cursor drags around "Visual feedback".
const BOX = { x: 250, y: 300, w: 1420, h: 236 };

export const Scene1Hook: React.FC = () => {
  const frame = useCurrentFrame();

  const path = [
    { f: 26, x: 640, y: 760 },
    { f: 42, x: BOX.x, y: BOX.y },
    { f: 46, x: BOX.x, y: BOX.y },
    { f: 68, x: BOX.x + BOX.w, y: BOX.y + BOX.h },
    { f: 78, x: BOX.x + BOX.w + 30, y: BOX.y + BOX.h + 60 },
  ];
  const cur = cursorAt(path, frame);
  const dragging = frame >= 46;
  const bw = dragging ? Math.max(0, Math.min(BOX.w, cur.x - BOX.x)) : 0;
  const bh = dragging ? Math.max(0, Math.min(BOX.h, cur.y - BOX.y)) : 0;
  const settled = frame >= 70;

  const names = copy.hook.agents;
  const rotStart = 86;
  const step = 8;
  // Each step: a quick 5-frame roll, then a hold.
  const k = Math.max(0, (frame - rotStart) / step);
  const base = Math.floor(k);
  const words = [...names.map((n) => `${n}.`), copy.hook.hand];
  const rollPos = Math.min(words.length - 1, base + ease.outStrong(Math.min(1, ((k - base) * step) / 5)));
  const final = frame >= rotStart + (words.length - 2) * step;
  const lineIn = prog(frame, 74, 12);

  return (
    <AbsoluteFill>
      <DotGrid reveal={prog(frame, 0, 34, ease.outStrong)} />

      {/* dragged mark */}
      {dragging ? (
        <div
          style={{
            position: 'absolute',
            left: BOX.x,
            top: BOX.y,
            width: bw,
            height: bh,
            border: `4px dashed ${c.sky500}`,
            background: 'rgba(56,189,248,0.08)',
            borderRadius: 14,
            boxSizing: 'border-box',
          }}
        />
      ) : null}
      {settled ? (
        <div style={{ position: 'absolute', left: BOX.x - 20, top: BOX.y - 20, transform: `scale(${popScale(frame, 71)})` }}>
          <MarkBadge label="M1" size={44} />
          <Sfx at={71} name="pop" volume={0.6} />
        </div>
      ) : null}

      {/* headline */}
      <div
        style={{
          position: 'absolute',
          left: BOX.x,
          top: BOX.y,
          width: BOX.w,
          height: BOX.h,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          fontFamily: font.sans,
          fontWeight: 600,
          fontSize: 172,
          letterSpacing: '-0.04em',
          color: c.fg,
          whiteSpace: 'nowrap',
        }}
      >
        <Typed text={copy.hook.title} start={6} seed="hook" speed={1.55} caretUntil={46} volume={0.38} />
      </div>

      {/* with <agent> */}
      <div
        style={{
          position: 'absolute',
          left: 0,
          right: 0,
          top: BOX.y + BOX.h + 40,
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'baseline',
          gap: 30,
          lineHeight: 1.2,
          fontFamily: font.sans,
          fontWeight: 600,
          fontSize: 120,
          letterSpacing: '-0.035em',
          color: c.fg,
          opacity: lineIn,
          transform: `translateY(${lerp(24, 0, lineIn)}px)`,
        }}
      >
        <span>with</span>
        {/* The roll window. The hidden word in flow gives the slot the same
            baseline as "with"; the clip lives on an inner layer so overflow
            doesn't move that baseline. */}
        <span style={{ position: 'relative', display: 'inline-block' }}>
          <span style={{ visibility: 'hidden' }}>{words[words.length - 1]}</span>
          <span style={{ position: 'absolute', left: 0, right: -40, top: 0, bottom: 0, overflow: 'hidden' }}>
            {words.map((w, i) => {
              if (Math.abs(i - rollPos) >= 1) return null;
              const last = i === words.length - 1;
              return (
                <span key={w} style={{ position: 'absolute', left: 0, top: `${(i - rollPos) * 100}%`, whiteSpace: 'nowrap', color: last ? c.fg : c.n400 }}>
                  {w}
                </span>
              );
            })}
          </span>
          {words.slice(1).map((_, i) => (
            <Sfx key={i} at={rotStart + i * step} name="tick" volume={0.35} />
          ))}
          {final ? <Underline start={rotStart + (words.length - 2) * step + 6} /> : null}
        </span>
      </div>

      <VoLine id="v1" at={HOOK_VO} />
      <Cursor path={path} holds={[{ from: 46, to: 69 }]} appearAt={24} hideAt={92} />
      <Sfx at={47} name="drag" volume={0.7} />
    </AbsoluteFill>
  );
};

const Underline: React.FC<{ start: number }> = ({ start }) => {
  const frame = useCurrentFrame();
  const p = prog(frame, start, 14, ease.snap);
  return (
    <svg width="100%" height="34" viewBox="0 0 600 34" preserveAspectRatio="none" style={{ position: 'absolute', left: 0, top: '84%', zIndex: -1 }}>
      <path
        d="M4 22 C 140 10, 330 8, 596 16"
        fill="none"
        style={{ stroke: c.key }}
        strokeWidth="13"
        strokeLinecap="round"
        pathLength={1}
        strokeDasharray="1"
        strokeDashoffset={1 - p}
      />
      <Sfx at={start} name="scribble" volume={0.8} />
    </svg>
  );
};
