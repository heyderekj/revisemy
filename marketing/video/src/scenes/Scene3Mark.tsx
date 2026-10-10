import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { FN } from '../components/FieldnoteSite';
import { at, btnCenter, MarkCard, PointMark, RectMark, region, ReviewHeader, ReviewPage, Section, SIDE, Toast } from '../components/Review';
import { Cursor, cursorAt, CursorKey, DotGrid, Icon, MarkBadge, Typed, typedEnd } from '../components/ui';
import { StepRail, voFrames, VoLine, voWord } from '../components/Vo';
import { appear, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';

const M1 = region(FN.m1);
const M3 = region(FN.m3);
const M2 = at(FN.m2.x, FN.m2.y);
const S1 = at(FN.s1.x, FN.s1.y);
const S2 = at(FN.s2.x, FN.s2.y);
const G1 = at(FN.g1.x, FN.g1.y);
const SHARE = btnCenter('share');
const CHANGES = btnCenter('changes');
const SIDE_X = SIDE.x + SIDE.w / 2;
// Sidebar geometry (every card is one line once written): My marks holds
// M1–M3, then Hints holds S1, S2 and the guest's G1 with its Accept row.
const CARD = 96;
const HINTS_TOP = SIDE.y + 56 + 3 * CARD + 2 * 10 + 16 + 14;
const G1_CARD_TOP = HINTS_TOP + 56 + 28 + 2 * (CARD + 10);
const ACCEPT = { x: SIDE.x + 16 + 14 + 48, y: G1_CARD_TOP + CARD + 24 };

// Every beat hangs off the voiceover and the typing, so timings follow the audio.
export const mk = (() => {
  const v3 = 30;
  const m1From = v3 + 26;
  const m1To = m1From + 30;
  const m1Pop = m1To + 2;
  const m1Card = m1To + 6;
  const v4 = v3 + voFrames('v3') + 4;
  const m1Type = v4 + 18;
  const m1Typed = Math.round(typedEnd(copy.review.m1.note, m1Type, 'm1', 1.45));
  const m2Click = Math.max(m1Typed, v4 + voFrames('v4')) + 22;
  const m2Card = m2Click + 5;
  const m2Type = m2Card + 14;
  const m2Typed = Math.round(typedEnd(copy.review.m2.note, m2Type, 'm2', 1.4));
  const v5 = m2Typed + 16;
  const m3From = v5 + 14;
  const m3To = m3From + 30;
  const m3Pop = m3To + 2;
  const m3Card = m3To + 6;
  const m3Type = m3Card + 16;
  const m3Typed = Math.round(typedEnd(copy.review.m3.note, m3Type, 'm3', 1.4));
  const v6 = Math.max(m3Typed, v5 + voFrames('v5')) + 16;
  const s1 = v6 + 6;
  const s2 = s1 + 10;
  const v7 = v6 + voFrames('v6') + 10;
  const share = v7 + 22;
  const g1 = share + 30;
  const accept = g1 + 46;
  const v8 = Math.max(accept + 30, v7 + voFrames('v7') + 8);
  const changes = v8 + 12;
  const end = changes + 54;
  return { v3, v4, v5, v6, v7, v8, accept, m1From, m1To, m1Pop, m1Card, m1Type, m1Typed, m2Click, m2Card, m2Type, m2Typed, m3From, m3To, m3Pop, m3Card, m3Type, m3Typed, s1, s2, share, g1, changes, end };
})();

export const MARK_DURATION = mk.end + 26;

const press = (frame: number, f: number) => interpolate(frame - f, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

/** A region being dragged out by the cursor, then settled. */
const dragged = (frame: number, path: CursorKey[], r: { x: number; y: number; w: number; h: number }, from: number, to: number) => {
  if (frame < from) return null;
  if (frame >= to + 2) return { ...r, drawing: false };
  const cur = cursorAt(path, frame);
  return { x: r.x, y: r.y, w: Math.max(0, cur.x - r.x), h: Math.max(0, cur.y - r.y), drawing: true };
};

export const Scene3Mark: React.FC = () => {
  const frame = useCurrentFrame();
  const t = mk;

  const path: CursorKey[] = [
    { f: 22, x: 1000, y: 1000 },
    { f: t.m1From - 4, x: M1.x, y: M1.y },
    { f: t.m1From, x: M1.x, y: M1.y },
    { f: t.m1To, x: M1.x + M1.w, y: M1.y + M1.h },
    { f: t.m1Card + 12, x: M1.x + M1.w + 70, y: M1.y + M1.h + 50 },
    { f: t.m2Click - 22, x: M1.x + M1.w + 70, y: M1.y + M1.h + 50 },
    { f: t.m2Click - 2, x: M2.x, y: M2.y },
    { f: t.m2Click + 18, x: M2.x + 60, y: M2.y + 60 },
    { f: t.m3From - 22, x: M2.x + 60, y: M2.y + 60 },
    { f: t.m3From - 2, x: M3.x, y: M3.y },
    { f: t.m3From, x: M3.x, y: M3.y },
    { f: t.m3To, x: M3.x + M3.w, y: M3.y + M3.h },
    { f: t.m3Card + 12, x: M3.x + M3.w - 80, y: M3.y + M3.h - 40 },
    { f: t.share - 20, x: M3.x + M3.w - 80, y: M3.y + M3.h - 40 },
    { f: t.share - 3, x: SHARE.x, y: SHARE.y + 4 },
    { f: t.share + 18, x: SHARE.x - 40, y: SHARE.y + 140 },
    { f: t.accept - 3, x: ACCEPT.x, y: ACCEPT.y },
    { f: t.accept + 16, x: ACCEPT.x - 40, y: ACCEPT.y + 40 },
    { f: t.changes - 20, x: ACCEPT.x - 40, y: ACCEPT.y + 40 },
    { f: t.changes - 3, x: CHANGES.x, y: CHANGES.y + 4 },
    { f: t.changes + 30, x: CHANGES.x - 60, y: CHANGES.y + 220 },
  ];
  const r1 = dragged(frame, path, M1, t.m1From, t.m1To);
  const r3 = dragged(frame, path, M3, t.m3From, t.m3To);

  const side = (fy: number) => ({ s: 1.65, fx: SIDE_X, fy, ax: 1290, ay: 500 });
  const cam = [
    { f: 0, ...IDENTITY },
    { f: 30, ...IDENTITY },
    { f: 50, s: 1.45, fx: M1.x + M1.w / 2, fy: M1.y + M1.h / 2, ax: 960, ay: 500 },
    { f: t.m1Pop + 6, s: 1.45, fx: M1.x + M1.w / 2, fy: M1.y + M1.h / 2, ax: 960, ay: 500 },
    { f: t.m1Card + 14, ...side(300) },
    { f: t.m1Typed + 6, ...side(300) },
    { f: t.m2Click - 10, s: 1.6, fx: M2.x, fy: M2.y, ax: 800, ay: 500 },
    { f: t.m2Card + 4, s: 1.6, fx: M2.x, fy: M2.y, ax: 800, ay: 500 },
    { f: t.m2Type, ...side(400) },
    { f: t.m2Typed + 4, ...side(400) },
    { f: t.m3From - 6, s: 1.2, fx: M3.x + M3.w / 2, fy: M3.y + M3.h / 2, ax: 960, ay: 560 },
    { f: t.m3Pop + 6, s: 1.2, fx: M3.x + M3.w / 2, fy: M3.y + M3.h / 2, ax: 960, ay: 560 },
    { f: t.m3Type, ...side(470) },
    { f: t.m3Typed + 4, ...side(470) },
    { f: t.s1 + 4, s: 1.12, fx: 1180, fy: 560, ax: 960, ay: 540 },
    { f: t.share - 10, s: 1.12, fx: 1180, fy: 560, ax: 960, ay: 540 },
    { f: t.share - 2, s: 1.55, fx: SHARE.x, fy: SHARE.y + 10, ax: 1150, ay: 330 },
    { f: t.share + 12, s: 1.55, fx: SHARE.x, fy: SHARE.y + 10, ax: 1150, ay: 330 },
    { f: t.g1 + 2, s: 1.45, fx: G1.x + 80, fy: G1.y + 40, ax: 900, ay: 500 },
    { f: t.g1 + 26, s: 1.45, fx: G1.x + 80, fy: G1.y + 40, ax: 900, ay: 500 },
    { f: t.accept - 10, ...side(ACCEPT.y - 60) },
    { f: t.accept + 22, ...side(ACCEPT.y - 60) },
    { f: t.changes - 4, s: 1.6, fx: CHANGES.x, fy: CHANGES.y + 10, ax: 1350, ay: 330 },
    { f: t.changes + 8, s: 1.6, fx: CHANGES.x, fy: CHANGES.y + 10, ax: 1350, ay: 330 },
    { f: t.changes + 26, ...IDENTITY },
    { f: t.end, s: 1.03, fx: 960, fy: 580 },
  ];
  const k = camAt(cam, frame);
  const requested = frame >= t.changes;

  // While v4 names each intent, ring the matching chip.
  const named = (word: string) => t.v4 + voWord('v4', word);
  const ringOn =
    frame >= named('keep') && frame < named('keep') + 22
      ? 'Keep this'
      : frame >= named('nice') && frame < named('keep')
        ? 'Nice to have'
        : frame >= named('must') && frame < named('nice')
          ? 'Must fix'
          : null;

  const accepted = frame >= t.accept + 2;
  const marks = (frame >= t.m1Card ? 1 : 0) + (frame >= t.m2Card ? 1 : 0) + (frame >= t.m3Card ? 1 : 0) + (accepted ? 1 : 0);

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />
      <Camera keys={cam}>
        <UrlBar />
        <ReviewPage
          header={<ReviewHeader pressed={{ share: press(frame, t.share), changes: press(frame, t.changes) }} active={{ changes: requested }} />}
          sidebar={
            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
              <Section title="My marks" count={marks}>
                {marks === 0 ? <div style={{ fontSize: 17, color: c.muted, padding: '4px 2px 6px' }}>Drag to mark a region, or click for a point.</div> : null}
                {frame >= t.m1Card ? (
                  <MarkCard
                    appearAt={t.m1Card}
                    badge={<MarkBadge label="M1" size={28} />}
                    label={copy.review.m1.severity}
                    status={frame > t.m1Typed + 6 ? 'Open' : undefined}
                    clamp={frame > t.m2Card}
                  >
                    {frame <= t.m1Typed + 6 ? <SeverityPicker selected="Must fix" ring={ringOn} at={t.m1Card + 4} /> : null}
                    <Typed text={copy.review.m1.note} start={t.m1Type} seed="m1" speed={1.45} caretUntil={t.m1Typed + 8} volume={0.28} />
                  </MarkCard>
                ) : null}
                {frame >= t.m2Card ? (
                  <MarkCard
                    appearAt={t.m2Card}
                    badge={<MarkBadge label="M2" size={28} />}
                    label={copy.review.m2.severity}
                    status={frame > t.m2Typed + 6 ? 'Open' : undefined}
                    clamp={frame > t.m3Card}
                  >
                    {frame <= t.m2Typed + 6 ? <SeverityPicker selected="Nice to have" at={t.m2Card + 4} /> : null}
                    <Typed text={copy.review.m2.note} start={t.m2Type} seed="m2" speed={1.4} caretUntil={t.m2Typed + 8} volume={0.28} />
                  </MarkCard>
                ) : null}
                {frame >= t.m3Card ? (
                  <MarkCard
                    appearAt={t.m3Card}
                    badge={<MarkBadge label="M3" size={28} />}
                    label={copy.review.m3.severity}
                    status={frame > t.m3Typed + 6 ? 'Open' : undefined}
                    clamp={frame > t.s1}
                  >
                    {frame <= t.m3Typed + 6 ? <SeverityPicker selected="Keep this" at={t.m3Card + 4} /> : null}
                    <Typed text={copy.review.m3.note} start={t.m3Type} seed="m3" speed={1.4} caretUntil={t.m3Typed + 8} volume={0.28} />
                  </MarkCard>
                ) : null}
                {accepted ? (
                  <MarkCard
                    appearAt={t.accept + 2}
                    clamp
                    badge={<MarkBadge label="M4" kind="guest" size={28} />}
                    label={`Nice to have · from ${copy.review.g1.who}`}
                    status="Open"
                  >
                    {copy.review.g1.note}
                  </MarkCard>
                ) : null}
              </Section>
              {frame >= t.s1 + 4 ? (
                <Section title="Hints" count={2 + (frame >= t.g1 + 6 && !accepted ? 1 : 0)} style={appear(frame, t.s1 + 4, 12, 12)}>
                  <div style={{ fontSize: 15, color: c.muted, marginTop: -4 }}>Suggestions until you accept them.</div>
                  <MarkCard dashed clamp badge={<MarkBadge label="S1" kind="hint" size={28} />} label={copy.review.s1.kind}>
                    {copy.review.s1.note}
                  </MarkCard>
                  <MarkCard dashed clamp appearAt={t.s2 + 4} badge={<MarkBadge label="S2" kind="hint" size={28} />} label={copy.review.s2.kind}>
                    {copy.review.s2.note}
                  </MarkCard>
                  {frame >= t.g1 + 6 && !accepted ? (
                    <MarkCard
                      appearAt={t.g1 + 6}
                      clamp
                      badge={<MarkBadge label="G1" kind="guest" size={28} />}
                      label={`Guest · ${copy.review.g1.who}`}
                      lit={prog(frame, t.accept - 14, 8)}
                      footer={<AcceptRow pressed={press(frame, t.accept)} />}
                    >
                      {copy.review.g1.note}
                    </MarkCard>
                  ) : null}
                </Section>
              ) : null}
            </div>
          }
          overlay={
            <>
              {r1 ? <RectMark {...r1} label="M1" popAt={t.m1Pop} /> : null}
              <PointMark x={M2.x} y={M2.y} label="M2" popAt={t.m2Click + 2} />
              {r3 ? <RectMark {...r3} label="M3" popAt={t.m3Pop} /> : null}
              <PointMark x={S1.x} y={S1.y} label="S1" kind="hint" popAt={t.s1} />
              <PointMark x={S2.x} y={S2.y} label="S2" kind="hint" popAt={t.s2} />
              <PointMark x={G1.x} y={G1.y} label={accepted ? "M4" : "G1"} kind="guest" popAt={t.g1} />
              <GuestBubble at={t.g1 + 6} x={G1.x + 26} y={G1.y + 26} />
              <Toast at={t.share + 3} until={t.share + 40} x={SHARE.x} y={SHARE.y + 46}>
                <Icon name="link" size={18} color={c.key} /> {copy.review.shared}
              </Toast>
              <Toast at={t.accept + 3} until={t.accept + 36} x={SIDE_X} y={ACCEPT.y - 150}>
                <Icon name="check" size={18} color={c.key} /> {copy.review.added}
              </Toast>
              <Toast at={t.changes + 4} until={t.end + 30} x={960} y={880}>
                <Icon name="check" size={20} color={c.key} /> {copy.review.requested}
              </Toast>
            </>
          }
        />
        <Cursor path={path} holds={[{ from: t.m1From, to: t.m1To }, { from: t.m3From, to: t.m3To }]} clicks={[t.m2Click, t.share, t.accept, t.changes]} appearAt={20} />
      </Camera>

      <StepRail step="Mark" />
      <VoLine id="v3" at={t.v3} />
      <VoLine id="v4" at={t.v4} />
      <VoLine id="v5" at={t.v5} />
      <VoLine id="v6" at={t.v6} />
      <VoLine id="v7" at={t.v7} />
      <VoLine id="v8" at={t.v8} />

      <Sfx at={t.m1From + 1} name="drag" volume={0.6} />
      <Sfx at={t.m1Pop} name="pop" volume={0.5} />
      <Sfx at={t.m2Click + 2} name="pop" volume={0.45} />
      <Sfx at={t.m3From + 1} name="drag" volume={0.6} />
      <Sfx at={t.m3Pop} name="pop" volume={0.5} />
      <Sfx at={t.s1} name="pop-high" volume={0.4} />
      <Sfx at={t.s2} name="pop-high" volume={0.4} />
      <Sfx at={t.g1} name="pop" volume={0.45} />
      <Sfx at={t.changes + 4} name="swipe" volume={0.4} />
      <Sfx at={t.end - 2} name="whoosh" volume={0.5} />
    </AbsoluteFill>
  );
};

const SEVERITIES = ['Must fix', 'Nice to have', 'Question', 'Keep this'];

/** hint.blade.php's actions: Accept turns a suggestion into a mark. */
const AcceptRow: React.FC<{ pressed: number }> = ({ pressed }) => (
  <div style={{ marginTop: 10, display: 'flex', gap: 6 }}>
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 6,
        height: 30,
        padding: '0 12px',
        borderRadius: 999,
        background: c.doneSoft,
        color: c.doneInk,
        fontSize: 15,
        fontWeight: 500,
        transform: `scale(${1 - pressed * 0.06})`,
      }}
    >
      <Icon name="check" size={14} /> Accept
    </span>
    <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 30, height: 30, borderRadius: 999, background: c.chip, color: c.n600, fontSize: 16 }}>×</span>
  </div>
);

/** The intent picker on a new mark; `ring` spotlights the one the voiceover is naming. */
const SeverityPicker: React.FC<{ selected: string; at: number; ring?: string | null }> = ({ selected, at, ring }) => {
  const frame = useCurrentFrame();
  return (
    <div style={{ display: 'flex', gap: 6, marginBottom: 8, flexWrap: 'wrap', ...appear(frame, at, 8, 6) }}>
      {SEVERITIES.map((s) => (
        <span
          key={s}
          style={{
            fontSize: 14,
            fontWeight: 500,
            padding: '4px 10px',
            borderRadius: 999,
            background: s === selected ? c.key : c.chip,
            color: s === selected ? c.keyInk : c.n600,
            boxShadow: ring === s ? `0 0 0 2px ${c.bg}, 0 0 0 4px ${c.fg}` : undefined,
            transform: ring === s ? 'scale(1.06)' : undefined,
          }}
        >
          {s}
        </span>
      ))}
    </div>
  );
};

const GuestBubble: React.FC<{ at: number; x: number; y: number }> = ({ at: start, x, y }) => {
  const frame = useCurrentFrame();
  if (frame < start) return null;
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
        zIndex: 6,
        ...appear(frame, start, 12, 10),
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

/** The review link and the handwritten aside: nobody signs in. */
export const UrlBar: React.FC<{ note?: boolean }> = ({ note = true }) => {
  const frame = useCurrentFrame();
  const p = prog(frame, 14, 18, ease.snap);
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
      {note ? (
        <span
          style={{
            fontFamily: font.hand,
            fontSize: 36,
            fontWeight: 600,
            color: c.fg,
            clipPath: `inset(-30% ${100 - p * 100}% -30% -4%)`,
            transform: 'rotate(-2deg)',
            display: 'inline-block',
            paddingRight: 16,
          }}
        >
          ← {copy.review.noAccount}
          <Sfx at={14} name="scribble" volume={0.4} />
        </span>
      ) : null}
    </div>
  );
};
