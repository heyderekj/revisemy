import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { spots } from '../components/MockSite';
import {
  btnCenter,
  HEAD_RECT,
  MarkCard,
  PointMark,
  RectMark,
  ReviewHeader,
  ReviewPage,
  Section,
  SIDE,
  siteAt,
  Toast,
} from '../components/Review';
import { Cursor, cursorAt, DotGrid, Icon, MarkBadge, Typed, typedEnd } from '../components/ui';
import { appear, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';

export const rv = (() => {
  const dragFrom = 54;
  const dragTo = 86;
  const m1Pop = 88;
  const m1Card = 92;
  const m1Type = 102;
  const m1Typed = Math.round(typedEnd(copy.review.m1.note, m1Type, 'm1', 1.5));
  const m2Click = m1Typed + 26;
  const m2Card = m2Click + 5;
  const m2Type = m2Click + 16;
  const m2Typed = Math.round(typedEnd(`“${copy.review.m2.suggested}”`, m2Type, 'm2', 1.7));
  const s1 = m2Typed + 14;
  const share = s1 + 32;
  const g1 = share + 20;
  const changes = g1 + 46;
  const end = changes + 52;
  return { dragFrom, dragTo, m1Pop, m1Card, m1Type, m1Typed, m2Click, m2Card, m2Type, m2Typed, s1, share, g1, changes, end };
})();

export const REVIEW_DURATION = rv.end + 26;

const press = (frame: number, at: number) =>
  interpolate(frame - at, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

export const Scene3Review: React.FC = () => {
  const frame = useCurrentFrame();
  const t = rv;
  const cta = siteAt(spots.cta.x, spots.cta.y);
  const hint = siteAt(spots.ctaHint.x, spots.ctaHint.y);
  const photo = siteAt(spots.photo.x, spots.photo.y);
  const share = btnCenter('share');
  const changes = btnCenter('changes');
  const H = HEAD_RECT;

  const path = [
    { f: 22, x: 1000, y: 980 },
    { f: t.dragFrom - 4, x: H.x, y: H.y },
    { f: t.dragFrom, x: H.x, y: H.y },
    { f: t.dragTo, x: H.x + H.w, y: H.y + H.h },
    { f: t.m1Card + 10, x: H.x + H.w + 60, y: H.y + H.h + 40 },
    { f: t.m2Click - 22, x: H.x + H.w + 60, y: H.y + H.h + 40 },
    { f: t.m2Click - 2, x: cta.x, y: cta.y },
    { f: t.s1 + 8, x: cta.x + 40, y: cta.y + 60 },
    { f: t.share - 3, x: share.x, y: share.y + 4 },
    { f: t.share + 18, x: share.x - 30, y: share.y + 120 },
    { f: t.changes - 3, x: changes.x, y: changes.y + 4 },
    { f: t.changes + 30, x: changes.x - 60, y: changes.y + 200 },
  ];
  const cur = cursorAt(path, frame);
  const drawing = frame >= t.dragFrom && frame < t.dragTo + 2;
  const rect =
    frame < t.dragFrom
      ? null
      : drawing
        ? { x: H.x, y: H.y, w: Math.max(0, cur.x - H.x), h: Math.max(0, cur.y - H.y) }
        : { x: H.x, y: H.y, w: H.w, h: H.h };

  const cam = [
    { f: 0, ...IDENTITY },
    { f: 34, ...IDENTITY },
    { f: 52, s: 1.5, fx: H.x + H.w / 2, fy: H.y + H.h / 2, ax: 960, ay: 560 },
    { f: t.dragTo + 4, s: 1.5, fx: H.x + H.w / 2, fy: H.y + H.h / 2, ax: 960, ay: 560 },
    { f: t.m1Type - 2, s: 1.7, fx: SIDE.x + 200, fy: 330, ax: 1250, ay: 500 },
    { f: t.m1Typed + 6, s: 1.7, fx: SIDE.x + 200, fy: 330, ax: 1250, ay: 500 },
    { f: t.m2Click - 6, s: 1.6, fx: cta.x, fy: cta.y, ax: 820, ay: 560 },
    { f: t.m2Click + 8, s: 1.6, fx: cta.x, fy: cta.y, ax: 820, ay: 560 },
    { f: t.m2Type, s: 1.6, fx: SIDE.x + 200, fy: 500, ax: 1250, ay: 540 },
    { f: t.m2Typed + 4, s: 1.6, fx: SIDE.x + 200, fy: 500, ax: 1250, ay: 540 },
    { f: t.s1 - 4, s: 1.5, fx: hint.x + 160, fy: hint.y - 30, ax: 860, ay: 560 },
    { f: t.s1 + 14, s: 1.5, fx: hint.x + 160, fy: hint.y - 30, ax: 860, ay: 560 },
    { f: t.share - 8, s: 1.55, fx: share.x, fy: share.y + 10, ax: 1150, ay: 330 },
    { f: t.share + 10, s: 1.55, fx: share.x, fy: share.y + 10, ax: 1150, ay: 330 },
    { f: t.g1 - 4, s: 1.4, fx: photo.x + 120, fy: photo.y + 60, ax: 960, ay: 540 },
    { f: t.g1 + 30, s: 1.4, fx: photo.x + 120, fy: photo.y + 60, ax: 960, ay: 540 },
    { f: t.changes - 6, s: 1.6, fx: changes.x, fy: changes.y + 10, ax: 1350, ay: 330 },
    { f: t.changes + 6, s: 1.6, fx: changes.x, fy: changes.y + 10, ax: 1350, ay: 330 },
    { f: t.changes + 22, ...IDENTITY },
    { f: t.end, s: 1.03, fx: 960, fy: 600 },
  ];
  const k = camAt(cam, frame);

  const requested = frame >= t.changes;

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />
      <Camera keys={cam}>
        <UrlBar />
        <ReviewPage
          header={
            <ReviewHeader
              pressed={{ share: press(frame, t.share), changes: press(frame, t.changes) }}
              active={{ changes: requested }}
            />
          }
          sidebar={
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              <Section title="My marks" count={(frame >= t.m1Card ? 1 : 0) + (frame >= t.m2Card ? 1 : 0)}>
                {frame < t.m1Card ? (
                  <div style={{ fontSize: 17, color: c.muted, padding: '4px 2px 6px' }}>Drag to mark a region, or click for a point.</div>
                ) : null}
                {frame >= t.m1Card ? (
                  <MarkCard
                    appearAt={t.m1Card}
                    badge={<MarkBadge label="M1" size={28} />}
                    label={copy.review.m1.severity}
                    status={frame > t.m1Typed + 6 ? (requested ? 'Open' : 'Open') : undefined}
                  >
                    {frame <= t.m1Typed + 6 ? <SeverityPicker selected="Must fix" at={t.m1Card + 4} /> : null}
                    <Typed text={copy.review.m1.note} start={t.m1Type} seed="m1" speed={1.5} caretUntil={t.m1Typed + 8} volume={0.28} />
                  </MarkCard>
                ) : null}
                {frame >= t.m2Card ? (
                  <MarkCard appearAt={t.m2Card} badge={<MarkBadge label="M2" size={28} />} label={copy.review.m2.severity} status={frame > t.m2Typed + 6 ? 'Open' : undefined}>
                    <span>{copy.review.m2.note}</span>
                    <div style={{ marginTop: 8, background: c.y50, borderRadius: 10, padding: '8px 12px', fontSize: 17 }}>
                      <span style={{ color: c.muted }}>Suggested: </span>
                      <Typed text={`“${copy.review.m2.suggested}”`} start={t.m2Type} seed="m2" speed={1.7} caretUntil={t.m2Typed + 8} volume={0.28} style={{ fontWeight: 600 }} />
                    </div>
                  </MarkCard>
                ) : null}
              </Section>
              {frame >= t.s1 + 4 ? (
                <Section title="Hints" count={1} style={appear(frame, t.s1 + 4, 12, 12)} right={<span style={{ fontSize: 14, color: c.sky700 }}>{copy.review.s1.label}</span>}>
                  <MarkCard dashed badge={<MarkBadge label="S1" kind="hint" size={28} />} label="Accessibility">
                    {copy.review.s1.note}
                  </MarkCard>
                </Section>
              ) : null}
              {frame >= t.g1 + 6 ? (
                <Section title="Guests" count={1} style={appear(frame, t.g1 + 6, 12, 12)}>
                  <MarkCard badge={<MarkBadge label="G1" kind="guest" size={28} />} label={`${copy.review.g1.who} (${copy.review.g1.role})`}>
                    {copy.review.g1.note}
                  </MarkCard>
                </Section>
              ) : null}
            </div>
          }
          overlay={
            <>
              {rect ? <RectMark {...rect} label="M1" drawing={drawing} popAt={t.m1Pop} /> : null}
              <PointMark x={cta.x} y={cta.y} label="M2" popAt={t.m2Click + 2} />
              <PointMark x={hint.x} y={hint.y} label="S1" kind="hint" popAt={t.s1} />
              <PointMark x={photo.x} y={photo.y} label="G1" kind="guest" popAt={t.g1} />
              <GuestBubble at={t.g1 + 4} x={photo.x + 30} y={photo.y + 26} />
              <Toast at={t.share + 3} until={t.share + 40} x={share.x} y={share.y + 46}>
                <Icon name="link" size={18} color={c.key} /> Guest link copied
              </Toast>
              <Toast at={t.changes + 4} until={t.end + 30} x={960} y={900}>
                <Icon name="check" size={20} color={c.key} /> Changes requested · 4 marks sent to your agent
              </Toast>
            </>
          }
        />
        <Cursor path={path} holds={[{ from: t.dragFrom, to: t.dragTo }]} clicks={[t.m2Click, t.share, t.changes]} appearAt={20} />
      </Camera>
      <Sfx at={t.dragFrom + 1} name="drag" volume={0.7} />
      <Sfx at={t.m1Pop} name="pop" volume={0.6} />
      <Sfx at={t.m2Click + 2} name="pop" volume={0.55} />
      <Sfx at={t.s1} name="pop-high" volume={0.5} />
      <Sfx at={t.g1} name="pop" volume={0.5} />
      <Sfx at={t.changes + 4} name="swipe" volume={0.5} />
      <Sfx at={t.end - 2} name="whoosh" volume={0.6} />
    </AbsoluteFill>
  );
};

const SeverityPicker: React.FC<{ selected: string; at: number }> = ({ selected, at }) => {
  const frame = useCurrentFrame();
  return (
    <div style={{ display: 'flex', gap: 6, marginBottom: 8, ...appear(frame, at, 8, 6) }}>
      {['Must fix', 'Nice to have', 'Question'].map((s) => (
        <span
          key={s}
          style={{
            fontSize: 14,
            fontWeight: 500,
            padding: '4px 10px',
            borderRadius: 999,
            background: s === selected ? c.key : c.chip,
            color: s === selected ? c.keyInk : c.n600,
          }}
        >
          {s}
        </span>
      ))}
    </div>
  );
};

const GuestBubble: React.FC<{ at: number; x: number; y: number }> = ({ at, x, y }) => {
  const frame = useCurrentFrame();
  if (frame < at) return null;
  return (
    <div
      style={{
        position: 'absolute',
        left: x,
        top: y,
        width: 300,
        background: c.raised,
        borderRadius: '4px 16px 16px 16px',
        padding: '12px 14px',
        boxShadow: shadow.float,
        fontFamily: font.sans,
        ...appear(frame, at, 12, 10),
        transformOrigin: 'top left',
      }}
    >
      <div style={{ fontSize: 14, color: c.muted, marginBottom: 4 }}>
        <b style={{ color: c.fg, fontWeight: 600 }}>{copy.review.g1.who}</b> · guest link
      </div>
      <div style={{ fontSize: 18, color: c.n800, lineHeight: 1.4 }}>{copy.review.g1.note}</div>
    </div>
  );
};

const UrlBar: React.FC = () => {
  const frame = useCurrentFrame();
  const note = prog(frame, 14, 18, ease.snap);
  return (
    <div style={{ position: 'absolute', left: 70, top: 26, display: 'flex', alignItems: 'center', gap: 18, fontFamily: font.sans }}>
      <span
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: 10,
          background: c.bg,
          borderRadius: 12,
          padding: '9px 16px',
          boxShadow: `0 0 0 1px ${c.ring}`,
          fontFamily: font.mono,
          fontSize: 18,
          color: c.n700,
        }}
      >
        <svg width="14" height="16" viewBox="0 0 14 16">
          <rect x="1" y="7" width="12" height="8" rx="2" style={{ fill: c.n400 }} />
          <path d="M4 7V5a3 3 0 016 0v2" style={{ stroke: c.n400 }} strokeWidth="2" fill="none" />
        </svg>
        {copy.chat.reviewUrl}
      </span>
      <span
        style={{
          fontFamily: font.hand,
          fontSize: 36,
          fontWeight: 600,
          color: c.fg,
          clipPath: `inset(-30% ${100 - note * 100}% -30% -4%)`,
          transform: 'rotate(-2deg)',
          display: 'inline-block',
          paddingRight: 16,
        }}
      >
        ← {copy.review.noAccount.toLowerCase()}
      </span>
      <Sfx at={14} name="scribble" volume={0.5} />
    </div>
  );
};
