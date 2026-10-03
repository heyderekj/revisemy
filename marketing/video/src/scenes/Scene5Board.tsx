import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { btnCenter, PAGE, ReviewHeader } from '../components/Review';
import { Cursor, DotGrid, Icon, MarkBadge, SignalTag, statusTone } from '../components/ui';
import { appear, lerp, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';

const COL_GAP = 16;
const COL_W = (PAGE.w - 40 - COL_GAP * 3) / 4;
const COL_TOP = PAGE.y + PAGE.header + 20;
const CARD_H = 104;
const colX = (i: number) => PAGE.x + 20 + i * (COL_W + COL_GAP);
const cardY = (idx: number) => COL_TOP + 64 + idx * (CARD_H + 12);

export const bd = (() => {
  const toResolved = 22;
  const verifyBtn = 56;
  const verify = 84;
  const toVerified = verify + 4;
  const approve = 142;
  const stamp = approve + 4;
  const tagline = stamp + 18;
  const end = tagline + 72;
  return { toResolved, verifyBtn, verify, toVerified, approve, stamp, tagline, end };
})();

export const BOARD_DURATION = bd.end;

export const Scene5Board: React.FC = () => {
  const frame = useCurrentFrame();
  const t = bd;
  const approveBtn = btnCenter('approve');
  const verifyPos = { x: colX(2) + COL_W - 90, y: COL_TOP + 30 };
  const pressApprove = interpolate(frame - t.approve, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  // Close enough to read the cards: follow them across, rise to Approve,
  // then pull back as the stamp lands.
  const mid12 = (colX(1) + colX(2) + COL_W) / 2;
  const mid23 = (colX(2) + colX(3) + COL_W) / 2;
  const cardsY = cardY(1) + CARD_H / 2 + 20;
  const cam = [
    { f: 0, s: 1.6, fx: colX(1) + COL_W / 2 + 120, fy: cardsY },
    { f: t.toResolved, s: 1.6, fx: colX(1) + COL_W / 2 + 120, fy: cardsY },
    { f: t.toResolved + 34, s: 1.55, fx: mid12 + 120, fy: cardsY },
    { f: t.verify - 10, s: 1.55, fx: colX(2) + COL_W / 2 + 60, fy: cardsY - 40 },
    { f: t.toVerified + 24, s: 1.55, fx: mid23 + 140, fy: cardsY },
    { f: t.approve - 8, s: 1.35, fx: approveBtn.x - 260, fy: approveBtn.y + 260 },
    { f: t.approve + 2, s: 1.35, fx: approveBtn.x - 260, fy: approveBtn.y + 260 },
    { f: t.stamp + 12, s: 1.0, fx: 960, fy: 540 },
    { f: t.end, s: 0.95, fx: 960, fy: 540 },
  ];
  const k = camAt(cam, frame);
  const approved = frame >= t.stamp;

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />
      <Camera keys={cam}>
        <div
          style={{
            position: 'absolute',
            left: PAGE.x,
            top: PAGE.y,
            width: PAGE.w,
            height: 1100,
            background: c.bg,
            borderRadius: '24px 24px 0 0',
            boxShadow: shadow.window,
            overflow: 'hidden',
            fontFamily: font.sans,
          }}
        >
          <ReviewHeader pass="Pass 2" pressed={{ approve: pressApprove }} />
        </div>

        {copy.board.columns.map((name, i) => {
          const count = i === 1 ? (frame < t.toResolved ? 4 : 0) : i === 2 ? (frame >= t.toResolved && frame < t.toVerified ? 4 : 0) : i === 3 && frame >= t.toVerified ? 4 : 0;
          return (
            <div
              key={name}
              style={{
                position: 'absolute',
                left: colX(i),
                top: COL_TOP,
                width: COL_W,
                height: 840,
                background: c.card,
                borderRadius: 20,
                fontFamily: font.sans,
                padding: '18px 16px',
                boxSizing: 'border-box',
              }}
            >
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <SignalTag tone={statusTone(name)} scale={1.15}>
                  {name}
                </SignalTag>
                <span style={{ fontSize: 16, color: c.muted, fontVariantNumeric: 'tabular-nums' }}>{count}</span>
                <div style={{ flex: 1 }} />
                {i === 2 && frame >= t.verifyBtn && frame < t.toVerified + 6 ? (
                  <span
                    style={{
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: 6,
                      height: 34,
                      padding: '0 14px',
                      borderRadius: 999,
                      background: c.doneSoft,
                      color: c.doneInk,
                      fontSize: 16,
                      fontWeight: 500,
                      transform: `scale(${1 - interpolate(frame - t.verify, [0, 2, 8], [0, 0.06, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })})`,
                      ...appear(frame, t.verifyBtn, 10, 4),
                    }}
                  >
                    <Icon name="check" size={15} /> {copy.board.verifyAll} 4
                  </span>
                ) : null}
              </div>
            </div>
          );
        })}

        {copy.fix.items.map((it, i) => {
          const m1 = prog(frame, t.toResolved + i * 6, 14, ease.inOutStrong);
          const m2 = prog(frame, t.toVerified + i * 6, 14, ease.inOutStrong);
          const col = 1 + m1 + m2;
          const x = lerp(colX(1), colX(2), m1) + (colX(3) - colX(2)) * m2 + 16;
          const moving = (m1 > 0 && m1 < 1) || (m2 > 0 && m2 < 1);
          const lift = Math.sin(Math.PI * (m1 > 0 && m1 < 1 ? m1 : m2 > 0 && m2 < 1 ? m2 : 0));
          const status = col >= 2.5 ? 'Verified' : col >= 1.5 ? 'Resolved' : 'In progress';
          const kind = it.id.startsWith('S') ? 'hint' : it.id.startsWith('G') ? 'guest' : 'mark';
          return (
            <React.Fragment key={it.id}>
              <div
                style={{
                  position: 'absolute',
                  left: x,
                  top: cardY(i) - lift * 14,
                  width: COL_W - 32,
                  height: CARD_H,
                  background: c.raised,
                  borderRadius: 14,
                  boxShadow: moving ? shadow.float : shadow.raised,
                  transform: `rotate(${lift * 1.5}deg) scale(${1 + lift * 0.03})`,
                  padding: 14,
                  boxSizing: 'border-box',
                  fontFamily: font.sans,
                  zIndex: moving ? 10 : 1,
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
                  <MarkBadge label={it.id} kind={kind} size={28} />
                  <div style={{ flex: 1 }} />
                  <SignalTag tone={statusTone(status)}>{status}</SignalTag>
                </div>
                <div style={{ fontSize: 18, color: c.n800 }}>{it.text}</div>
              </div>
              <Sfx at={t.toResolved + i * 6} name="slide" volume={0.5} />
              <Sfx at={t.toVerified + i * 6 + 12} name="tick" volume={0.4} />
            </React.Fragment>
          );
        })}

        {approved ? <Stamp at={t.stamp} /> : null}

        <Cursor
          path={[
            { f: 30, x: 1300, y: 900 },
            { f: t.verify - 2, x: verifyPos.x, y: verifyPos.y },
            { f: t.verify + 24, x: verifyPos.x + 40, y: verifyPos.y + 220 },
            { f: t.approve - 2, x: approveBtn.x, y: approveBtn.y },
            { f: t.approve + 30, x: approveBtn.x - 80, y: approveBtn.y + 300 },
          ]}
          clicks={[t.verify, t.approve]}
          appearAt={28}
          hideAt={t.tagline}
        />
      </Camera>

      <Tagline at={t.tagline} />
      <Sfx at={t.stamp} name="stamp" volume={0.55} />
      <Sfx at={t.stamp + 3} name="chime" volume={0.6} />
    </AbsoluteFill>
  );
};

const Stamp: React.FC<{ at: number }> = ({ at }) => {
  const frame = useCurrentFrame();
  const tt = frame - at;
  const s = interpolate(tt, [0, 5, 9], [1.6, 0.97, 1], { extrapolateRight: 'clamp' });
  const o = interpolate(tt, [0, 3], [0, 1], { extrapolateRight: 'clamp' });
  return (
    <div
      style={{
        position: 'absolute',
        left: 960,
        top: 520,
        transform: `translate(-50%, -50%) rotate(-6deg) scale(${s})`,
        opacity: o,
        zIndex: 40,
        background: c.glass,
        border: `5px solid ${c.done}`,
        color: c.doneInk,
        borderRadius: 22,
        padding: '22px 40px',
        fontFamily: font.sans,
        fontWeight: 700,
        fontSize: 64,
        letterSpacing: '-0.02em',
        display: 'flex',
        alignItems: 'center',
        gap: 20,
        boxShadow: '0 30px 80px -30px rgba(0,0,0,0.4)',
        whiteSpace: 'nowrap',
      }}
    >
      <span style={{ width: 64, height: 64, borderRadius: '50%', background: c.done, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
        <Icon name="check" size={40} color="#fff" />
      </span>
      {copy.board.approved}
    </div>
  );
};

const Tagline: React.FC<{ at: number }> = ({ at }) => {
  const frame = useCurrentFrame();
  if (frame < at) return null;
  const p = prog(frame, at, 14);
  return (
    <div
      style={{
        position: 'absolute',
        left: 0,
        right: 0,
        top: 760,
        textAlign: 'center',
        fontFamily: font.sans,
        fontWeight: 600,
        fontSize: 54,
        letterSpacing: '-0.03em',
        color: c.fg,
        opacity: p,
        transform: `translateY(${lerp(20, 0, p)}px)`,
      }}
    >
      <span style={{ background: c.glass, borderRadius: 18, padding: '14px 28px', boxShadow: shadow.float }}>
        {copy.board.tagline}
      </span>
    </div>
  );
};
