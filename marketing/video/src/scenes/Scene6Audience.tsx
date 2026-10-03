import React from 'react';
import { AbsoluteFill, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera } from '../components/Camera';
import { AppIcon, Chip, DotGrid, Icon, MarkBadge, SignalTag, statusTone, Typed } from '../components/ui';
import { appear, lerp, popScale, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';

const CELL = { y: 290, w: 540, h: 540, gap: 40 };
const cellX = (i: number) => (1920 - (CELL.w * 3 + CELL.gap * 2)) / 2 + i * (CELL.w + CELL.gap);
const cellAt = (i: number) => 22 + i * 12;
// each card's little animation gets the camera to itself
const VIZ = [58, 142, 222];
const SOURCES = 300;

export const AUDIENCE_DURATION = 360;

const cellCentre = (i: number) => ({ fx: cellX(i) + CELL.w / 2, fy: CELL.y + CELL.h / 2 + 10 });
const TOUR = [
  { f: 0, s: 1, fx: 960, fy: 540 },
  { f: VIZ[0] - 10, s: 1, fx: 960, fy: 540 },
  { f: VIZ[0] + 4, s: 1.55, ...cellCentre(0), ax: 960, ay: 560 },
  { f: VIZ[1] - 12, s: 1.55, ...cellCentre(0), ax: 960, ay: 560 },
  { f: VIZ[1] + 2, s: 1.55, ...cellCentre(1), ax: 960, ay: 560 },
  { f: VIZ[2] - 12, s: 1.55, ...cellCentre(1), ax: 960, ay: 560 },
  { f: VIZ[2] + 2, s: 1.55, ...cellCentre(2), ax: 960, ay: 560 },
  { f: SOURCES - 24, s: 1.55, ...cellCentre(2), ax: 960, ay: 560 },
  { f: SOURCES - 6, s: 0.92, fx: 960, fy: 500, ax: 960, ay: 470 },
];

export const Scene6Audience: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <AbsoluteFill style={{ fontFamily: font.sans }}>
      <DotGrid />
      <Camera keys={TOUR}>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 120, textAlign: 'center', ...appear(frame, 4, 14, 16) }}>
        <div style={{ fontFamily: font.mono, fontSize: 20, letterSpacing: '0.14em', textTransform: 'uppercase', color: c.muted }}>
          Agencies · Freelancers · Teams
        </div>
        <div style={{ fontSize: 76, fontWeight: 600, letterSpacing: '-0.035em', color: c.fg, marginTop: 14 }}>{copy.audience.kicker}</div>
      </div>

      {copy.audience.cells.map((cell, i) => (
        <div
          key={cell.n}
          style={{
            position: 'absolute',
            left: cellX(i),
            top: CELL.y,
            width: CELL.w,
            height: CELL.h,
            background: c.raised,
            borderRadius: 28,
            boxShadow: shadow.window,
            padding: 34,
            boxSizing: 'border-box',
            display: 'flex',
            flexDirection: 'column',
            ...appear(frame, cellAt(i), 16, 40),
          }}
        >
          <div style={{ fontFamily: font.mono, fontSize: 20, color: c.muted }}>{cell.n}</div>
          <div style={{ fontSize: 54, fontWeight: 600, letterSpacing: '-0.03em', color: c.fg, marginTop: 6 }}>{cell.who}</div>
          <div style={{ fontSize: 26, lineHeight: 1.35, color: c.n600, marginTop: 10 }}>{cell.line}</div>
          <div style={{ flex: 1 }} />
          <div style={{ height: 210, background: c.card, borderRadius: 18, position: 'relative', overflow: 'hidden' }}>
            {i === 0 ? <AgencyViz /> : i === 1 ? <FreelanceViz /> : <TeamViz />}
          </div>
          <Sfx at={cellAt(i) + 2} name="pop" volume={0.45} />
        </div>
      ))}

      </Camera>
      <div style={{ position: 'absolute', left: 0, right: 0, top: 890, display: 'flex', justifyContent: 'center', gap: 14, alignItems: 'center' }}>
        <span style={{ fontSize: 24, color: c.muted, marginRight: 6, opacity: prog(frame, SOURCES - 6, 10) }}>Review anything visual:</span>
        {copy.audience.sources.map((s, i) => {
          const at = SOURCES + i * 7;
          return (
            <span key={s} style={{ transform: `scale(${popScale(frame, at)})` }}>
              <Chip style={{ fontSize: 24, padding: '10px 20px', borderRadius: 12, background: c.raised, color: c.fg, boxShadow: shadow.raised }}>{s}</Chip>
              <Sfx at={at} name="tick" volume={0.4} />
            </span>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};

/** Agency: a link goes out, the client marks on it. */
const AgencyViz: React.FC = () => {
  const frame = useCurrentFrame();
  const base = VIZ[0];
  return (
    <>
      <div style={{ position: 'absolute', left: 22, top: 24, display: 'flex', alignItems: 'center', gap: 10, ...appear(frame, base, 10, 8) }}>
        <AppIcon size={30} style={{ borderRadius: 8 }} />
        <span style={{ fontFamily: font.mono, fontSize: 19, background: c.raised, borderRadius: 10, padding: '7px 12px', color: c.n700, boxShadow: shadow.raised }}>
          <Typed text="revisemy.com/r/k7Q2xb" start={base + 6} seed="agency" speed={1.1} caretUntil={base + 40} volume={0.18} />
        </span>
      </div>
      <div style={{ position: 'absolute', left: 22, top: 96, right: 22, height: 92, background: c.raised, borderRadius: 12, ...appear(frame, base + 40, 12, 10) }}>
        <div style={{ position: 'absolute', left: 16, top: 16, width: 180, height: 14, borderRadius: 4, background: c.n150 }} />
        <div style={{ position: 'absolute', left: 16, top: 40, width: 130, height: 14, borderRadius: 4, background: c.n150 }} />
        <div
          style={{
            position: 'absolute',
            left: 230,
            top: 14,
            width: lerp(0, 190, prog(frame, base + 54, 14)),
            height: 60,
            border: `3px solid rgba(255,197,61,0.9)`,
            background: 'rgba(255,197,61,0.12)',
            borderRadius: 8,
          }}
        />
        {frame >= base + 70 ? (
          <div style={{ position: 'absolute', left: 218, top: 2, transform: `scale(${popScale(frame, base + 70)})` }}>
            <MarkBadge label="G1" kind="guest" size={28} />
          </div>
        ) : null}
        <span style={{ position: 'absolute', right: 14, bottom: 8, fontFamily: font.hand, fontSize: 28, fontWeight: 600, color: c.fg, opacity: prog(frame, base + 76, 10) }}>
          client, no login
        </span>
      </div>
      <Sfx at={base + 70} name="pop" volume={0.35} />
    </>
  );
};

/** Freelancer: the email chain gets struck out, one link replaces it. */
const FreelanceViz: React.FC = () => {
  const frame = useCurrentFrame();
  const base = VIZ[1];
  const lines = ['Re: Re: homepage feedback', 'Fwd: hero_v7_FINAL(2).png', 'Re: "which button?"'];
  const linkAt = base + 64;
  return (
    <>
      {lines.map((l, i) => {
        const strike = prog(frame, base + 14 + i * 12, 10, ease.snap);
        const gone = prog(frame, linkAt - 4, 12);
        return (
          <div
            key={l}
            style={{
              position: 'absolute',
              left: 22,
              top: 20 + i * 48,
              right: 22,
              height: 38,
              display: 'flex',
              alignItems: 'center',
              gap: 10,
              fontSize: 19,
              color: c.n600,
              opacity: (1 - gone * 0.75) * prog(frame, base + i * 4, 8),
            }}
          >
            <span style={{ fontSize: 18 }}>✉︎</span>
            <span style={{ position: 'relative' }}>
              {l}
              <span style={{ position: 'absolute', left: 0, top: '52%', height: 2.5, width: `${strike * 100}%`, background: c.problem }} />
            </span>
            {strike > 0 && strike < 0.5 ? <Sfx at={base + 14 + i * 12} name="scribble" volume={0.25} /> : null}
          </div>
        );
      })}
      <div
        style={{
          position: 'absolute',
          left: '50%',
          top: 120,
          transform: `translateX(-50%) scale(${popScale(frame, linkAt)})`,
          background: c.key,
          color: c.keyInk,
          borderRadius: 12,
          padding: '12px 18px',
          fontSize: 21,
          fontWeight: 600,
          display: 'flex',
          alignItems: 'center',
          gap: 10,
          boxShadow: shadow.float,
          whiteSpace: 'nowrap',
        }}
      >
        <Icon name="link" size={18} /> One review link
      </div>
      <Sfx at={linkAt} name="pop-high" volume={0.4} />
    </>
  );
};

/** Team: everyone's mark moves through to verified. */
const TeamViz: React.FC = () => {
  const frame = useCurrentFrame();
  const base = VIZ[2];
  const rows = [
    { who: 'Design', id: 'M1', kind: 'mark' as const },
    { who: 'Agent', id: 'S1', kind: 'hint' as const },
    { who: 'Eng', id: 'M2', kind: 'mark' as const },
  ];
  const statuses = ['Open', 'In progress', 'Resolved', 'Verified'];
  return (
    <>
      {rows.map((r, i) => {
        const step = Math.max(0, Math.min(3, Math.floor((frame - base - i * 6) / 14)));
        return (
          <div
            key={r.id}
            style={{
              position: 'absolute',
              left: 22,
              right: 22,
              top: 22 + i * 58,
              height: 46,
              background: c.raised,
              borderRadius: 12,
              display: 'flex',
              alignItems: 'center',
              gap: 12,
              padding: '0 12px',
              ...appear(frame, base + i * 5, 10, 8),
            }}
          >
            <MarkBadge label={r.id} kind={r.kind} size={28} />
            <span style={{ fontSize: 19, color: c.n700 }}>{r.who}</span>
            <div style={{ flex: 1 }} />
            <SignalTag tone={statusTone(statuses[step])}>{statuses[step]}</SignalTag>
          </div>
        );
      })}
      {[1, 2, 3].map((s) => (
        <Sfx key={s} at={base + s * 14 + 12} name="tick" volume={0.3} />
      ))}
    </>
  );
};
