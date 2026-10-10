import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { FieldnoteSite, FN, HEADLINE_AFTER, HEADLINE_BEFORE, SITE_H, SITE_W } from '../components/FieldnoteSite';
import { at, btnCenter, MarkCard, PointMark, RectMark, region, ReviewHeader, ReviewPage, Section, SIDE } from '../components/Review';
import { Cursor, CursorKey, DotGrid, Icon, MarkBadge } from '../components/ui';
import { StepRail, voFrames, VoLine, voWord } from '../components/Vo';
import { lerp, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font } from '../theme';
import { UrlBar } from './Scene3Mark';

type Rect = { x: number; y: number; w: number; h: number };

const M1 = region(FN.m1);
const M3 = region(FN.m3);
const M2 = at(FN.m2.x, FN.m2.y);
const M4 = at(FN.g1.x, FN.g1.y + 0.075);
const APPROVE = btnCenter('approve');

// What each fix's before/after crop shows, as fractions of the shot.
const CROPS: Record<'M1' | 'M2' | 'M4', { r: Rect; layout: 'stack' | 'row' }> = {
  M1: { r: FN.m1, layout: 'stack' },
  M2: { r: { x: 0.06, y: 0.44, w: 0.36, h: 0.13 }, layout: 'stack' },
  M4: { r: { x: 0.6, y: 0.16, w: 0.34, h: 0.42 }, layout: 'row' },
};

// Card sizes in the sidebar: one line when closed, room for the evidence when open.
const INNER = SIDE.w - 32 - 28; // section and card padding
const cropH = (id: keyof typeof CROPS) => {
  const { r, layout } = CROPS[id];
  const w = layout === 'stack' ? INNER : (INNER - 10) / 2;
  return (w * r.h * SITE_H) / (r.w * SITE_W);
};
const CLOSED = 96;
const openH = (id: keyof typeof CROPS) => {
  const label = 26;
  const crops = CROPS[id].layout === 'stack' ? 2 * (label + cropH(id)) + 10 : label + cropH(id);
  return 14 + 38 + 52 + 12 + crops + 12 + 32 + 14;
};

export const vf = (() => {
  const v10 = 16;
  const open1 = 20;
  const v11 = v10 + voFrames('v10') + 6;
  const verify1 = v11 + voWord('v11', 'verify') + 4;
  const open2 = verify1 + 14;
  const verify2 = open2 + 44;
  const open4 = verify2 + 14;
  const verify4 = Math.max(open4 + 44, v11 + voFrames('v11') - 6);
  const v12 = Math.max(verify4 + 16, v11 + voFrames('v11') + 8);
  const approve = v12 + voWord('v12', 'approve') + 6;
  const stamp = approve + 4;
  const end = Math.max(stamp + 54, v12 + voFrames('v12') + 20);
  return { v10, v11, v12, open1, verify1, open2, verify2, open4, verify4, approve, stamp, end };
})();

export const VERIFY_DURATION = vf.end + 10;

const press = (frame: number, f: number) => interpolate(frame - f, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

export const Scene5Verify: React.FC = () => {
  const frame = useCurrentFrame();
  const t = vf;

  // Which card is open, and how far, at this frame.
  const openness = (id: 'M1' | 'M2' | 'M4') => {
    const [from, to] = id === 'M1' ? [t.open1, t.verify1] : id === 'M2' ? [t.open2, t.verify2] : [t.open4, t.verify4];
    return prog(frame, from, 14, ease.drawer) * (1 - prog(frame, to + 8, 12, ease.drawer));
  };
  const h = (id: 'M1' | 'M2' | 'M4') => lerp(CLOSED, openH(id), openness(id));
  const verified = (id: 'M1' | 'M2' | 'M4') => frame >= (id === 'M1' ? t.verify1 : id === 'M2' ? t.verify2 : t.verify4);

  // Card tops, top to bottom: M1, M2, M3, M4 in My marks.
  const top0 = SIDE.y + 56 + 44 + 10;
  const tops = {
    M1: top0,
    M2: top0 + h('M1') + 10,
    M3: top0 + h('M1') + h('M2') + 20,
    M4: top0 + h('M1') + h('M2') + CLOSED + 30,
  };
  // Fully open positions, for the camera and the cursor.
  const openTop = {
    M1: top0,
    M2: top0 + CLOSED + 10,
    M4: top0 + 2 * CLOSED + CLOSED + 30,
  };
  const verifyBtn = (id: 'M1' | 'M2' | 'M4') => ({ x: SIDE.x + 16 + 14 + 44, y: openTop[id] + openH(id) - 14 - 16 });
  const side = (id: 'M1' | 'M2' | 'M4') => ({ s: 1.55, fx: SIDE.x + SIDE.w / 2, fy: openTop[id] + openH(id) / 2, ax: 1240, ay: 520 });

  const cam = [
    { f: 0, ...IDENTITY },
    { f: t.open1 + 6, ...side('M1') },
    { f: t.verify1 + 10, ...side('M1') },
    { f: t.open2 + 12, ...side('M2') },
    { f: t.verify2 + 10, ...side('M2') },
    { f: t.open4 + 12, ...side('M4') },
    { f: t.verify4 + 10, ...side('M4') },
    { f: t.approve - 12, s: 1.5, fx: APPROVE.x - 80, fy: APPROVE.y + 40, ax: 1300, ay: 300 },
    { f: t.approve + 2, s: 1.5, fx: APPROVE.x - 80, fy: APPROVE.y + 40, ax: 1300, ay: 300 },
    { f: t.stamp + 12, s: 1.0, fx: 960, fy: 560 },
    { f: t.end, s: 0.96, fx: 960, fy: 560 },
  ];
  const k = camAt(cam, frame);

  const path: CursorKey[] = [
    { f: t.open1, x: 1200, y: 900 },
    { f: t.verify1 - 2, ...verifyBtn('M1') },
    { f: t.verify2 - 2, ...verifyBtn('M2') },
    { f: t.verify4 - 2, ...verifyBtn('M4') },
    { f: t.approve - 2, x: APPROVE.x, y: APPROVE.y },
    { f: t.approve + 30, x: APPROVE.x - 90, y: APPROVE.y + 260 },
  ];

  const card = (id: 'M1' | 'M2' | 'M4', label: string, note: string, kind: 'mark' | 'guest' = 'mark') => (
    <div style={{ height: h(id), overflow: 'hidden', borderRadius: 14 }}>
      <MarkCard
        badge={<MarkBadge label={id} kind={kind} size={28} />}
        label={label}
        status={verified(id) ? 'Verified' : 'Resolved'}
        lit={openness(id)}
        clamp={openness(id) < 0.5}
        footer={
          <div style={{ opacity: openness(id) }}>
            <Evidence id={id} />
            <VerifyRow pressed={press(frame, id === 'M1' ? t.verify1 : id === 'M2' ? t.verify2 : t.verify4)} done={verified(id)} />
          </div>
        }
      >
        {note}
      </MarkCard>
    </div>
  );

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />
      <Camera keys={cam}>
        <UrlBar note={false} />
        <ReviewPage
          header={<ReviewHeader pass="Pass 2" pressed={{ approve: press(frame, t.approve) }} />}
          site={{ headline: HEADLINE_AFTER, cta: 1, mugLogo: 1 }}
          sidebar={
            <Section title="My marks" count={4}>
              <div style={{ fontSize: 15, lineHeight: 1.45, color: c.muted, marginTop: -4, height: 44 }}>
                Your agent fixed 3 marks. Check each one, then verify or reopen.
              </div>
              {card('M1', copy.review.m1.severity, copy.review.m1.note)}
              {card('M2', copy.review.m2.severity, copy.review.m2.note)}
              <div style={{ height: CLOSED, overflow: 'hidden', borderRadius: 14 }}>
                <MarkCard clamp badge={<MarkBadge label="M3" size={28} />} label={copy.review.m3.severity} status="Open">
                  {copy.review.m3.note}
                </MarkCard>
              </div>
              {card('M4', `Nice to have · from ${copy.review.g1.who}`, copy.review.g1.note, 'guest')}
            </Section>
          }
          overlay={
            <>
              <RectMark {...M1} label="M1" popAt={-10} faded={verified('M1') ? 1.2 : 0.6} />
              <PointMark x={M2.x} y={M2.y} label="M2" popAt={-10} faded={verified('M2') ? 1.2 : 0.6} />
              <RectMark {...M3} label="M3" popAt={-10} />
              <PointMark x={M4.x} y={M4.y} label="M4" kind="guest" popAt={-10} faded={verified('M4') ? 1.2 : 0.6} />
              {frame >= t.stamp ? <Stamp at={t.stamp} /> : null}
            </>
          }
        />
        <Cursor path={path} clicks={[t.verify1, t.verify2, t.verify4, t.approve]} appearAt={t.open1} hideAt={t.stamp + 20} />
      </Camera>

      <StepRail step="Verify" />
      <VoLine id="v10" at={t.v10} />
      <VoLine id="v11" at={t.v11} />
      <VoLine id="v12" at={t.v12} />

      {(['verify1', 'verify2', 'verify4'] as const).map((key) => (
        <Sfx key={key} at={t[key] + 2} name="tick" volume={0.5} />
      ))}
      <Sfx at={t.stamp} name="stamp" volume={0.5} />
      <Sfx at={t.stamp + 3} name="chime" volume={0.5} />
    </AbsoluteFill>
  );
};

/** The fix's before and after, cut from the two passes at the marked spot. */
const Evidence: React.FC<{ id: 'M1' | 'M2' | 'M4' }> = ({ id }) => {
  const { r, layout } = CROPS[id];
  const w = layout === 'stack' ? INNER : (INNER - 10) / 2;
  const ch = cropH(id);
  const crop = (after: boolean) => {
    const W = w / r.w;
    return (
      <div>
        <div style={{ height: 26, display: 'flex', alignItems: 'center', gap: 6, fontSize: 14, fontWeight: 500, color: after ? c.doneInk : c.muted }}>
          {after ? 'After · Pass 2' : 'Before · Pass 1'}
        </div>
        <div style={{ position: 'relative', width: w, height: ch, overflow: 'hidden', borderRadius: 8, boxShadow: `0 0 0 1px ${c.ring}` }}>
          <div style={{ position: 'absolute', left: -r.x * W, top: (-r.y * W * SITE_H) / SITE_W }}>
            <FieldnoteSite width={W} headline={after ? HEADLINE_AFTER : HEADLINE_BEFORE} cta={after ? 1 : 0} mugLogo={after ? 1 : 0} />
          </div>
        </div>
      </div>
    );
  };
  return (
    <div style={{ marginTop: 12, display: 'flex', flexDirection: layout === 'stack' ? 'column' : 'row', gap: 10 }}>
      {crop(false)}
      {crop(true)}
    </div>
  );
};

/** mark-card.blade.php's controls for a resolved mark. */
const VerifyRow: React.FC<{ pressed: number; done: boolean }> = ({ pressed, done }) => (
  <div style={{ marginTop: 12, display: 'flex', gap: 6 }}>
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 6,
        height: 32,
        padding: '0 13px',
        borderRadius: 999,
        background: done ? c.done : c.doneSoft,
        color: done ? '#fff' : c.doneInk,
        fontSize: 15,
        fontWeight: 500,
        transform: `scale(${1 - pressed * 0.06})`,
      }}
    >
      <Icon name="check" size={14} /> {done ? 'Verified' : 'Verify'}
    </span>
    <span style={{ display: 'inline-flex', alignItems: 'center', height: 32, padding: '0 13px', borderRadius: 999, background: c.chip, color: c.n700, fontSize: 15, fontWeight: 500 }}>
      Reopen
    </span>
  </div>
);

const Stamp: React.FC<{ at: number }> = ({ at: start }) => {
  const frame = useCurrentFrame();
  const tt = frame - start;
  const s = interpolate(tt, [0, 5, 9], [1.6, 0.97, 1], { extrapolateRight: 'clamp' });
  const o = interpolate(tt, [0, 3], [0, 1], { extrapolateRight: 'clamp' });
  return (
    <div
      style={{
        position: 'absolute',
        left: 760,
        top: 600,
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
      {copy.verify.approved}
    </div>
  );
};
