import React from 'react';
import { AbsoluteFill, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { AgentText, ChatWindow, Composer, InlineReviewCard, Row, ToolCall, UserBubble } from '../components/Chat';
import { FN, HEADLINE_AFTER, HEADLINE_BEFORE } from '../components/FieldnoteSite';
import { at, PointMark, RectMark, region, ReviewHeader, ReviewPage } from '../components/Review';
import { AppIcon, DotGrid, MarkBadge, SignalTag, Streamed } from '../components/ui';
import { StepRail, voFrames, VoLine } from '../components/Vo';
import { appear, lerp, popScale, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';
import { UrlBar } from './Scene3Mark';

const M1 = region(FN.m1);
const M3 = region(FN.m3);
const M2 = at(FN.m2.x, FN.m2.y);
const G1 = at(FN.g1.x, FN.g1.y);

export const fx = (() => {
  const notify = 14;
  const get = 28;
  const getDone = 50;
  const packet = 54;
  const v9 = 78;
  const plan = v9 + 4;
  // Pass 2: the page, with each fix landing where it was marked
  const page = plan + 64;
  const m1 = page + 22;
  const m1Del = m1 + 10; // deleting the old headline
  const m1Type = m1Del + Math.ceil(HEADLINE_BEFORE.length / 2) + 4;
  const m1Done = m1Type + HEADLINE_AFTER.length + 4;
  const m2 = m1Done + 22;
  const m2Done = m2 + 16;
  const g1 = m2Done + 24;
  const g1Done = g1 + 16;
  const m3 = g1Done + 22;
  const resolve = Math.max(m3 + 40, v9 + voFrames('v9') + 6);
  const resolveDone = resolve + 22;
  const end = resolveDone + 34;
  return { notify, get, getDone, packet, v9, plan, page, m1, m1Del, m1Type, m1Done, m2, m2Done, g1, g1Done, m3, resolve, resolveDone, end };
})();

export const FIX_DURATION = fx.end + 12;

/** The headline as the agent edits it: the old line deletes, the new one types in. */
const headlineAt = (frame: number) => {
  const t = fx;
  if (frame < t.m1Del) return { text: HEADLINE_BEFORE, caret: frame >= t.m1 };
  if (frame < t.m1Type) {
    const gone = Math.min(HEADLINE_BEFORE.length, (frame - t.m1Del) * 2);
    return { text: HEADLINE_BEFORE.slice(0, HEADLINE_BEFORE.length - gone), caret: true };
  }
  const n = Math.min(HEADLINE_AFTER.length, frame - t.m1Type);
  return { text: HEADLINE_AFTER.slice(0, n), caret: frame < t.m1Done + 8 };
};

const statusOf = (frame: number, start: number, done: number) => (frame >= done ? 'Resolved' : frame >= start ? 'In progress' : 'Open');

export const Scene4Fix: React.FC = () => {
  const frame = useCurrentFrame();
  const t = fx;

  const chatOut = prog(frame, t.page - 4, 18, ease.inOutStrong);
  const pageIn = prog(frame, t.page, 18, ease.outStrong);

  const chatCam = [
    { f: 0, s: 1.05, fx: 960, fy: 540 },
    { f: 20, s: 1.4, fx: 960, fy: 700, ax: 960, ay: 520 },
    { f: t.plan, s: 1.4, fx: 960, fy: 720, ax: 960, ay: 520 },
  ];
  const pageCam = [
    { f: t.page, s: 1.0, fx: 960, fy: 560 },
    { f: t.m1 - 4, s: 1.5, fx: M1.x + M1.w / 2, fy: M1.y + M1.h / 2, ax: 960, ay: 500 },
    { f: t.m1Done + 10, s: 1.5, fx: M1.x + M1.w / 2, fy: M1.y + M1.h / 2, ax: 960, ay: 500 },
    { f: t.m2 - 4, s: 1.7, fx: M2.x + 40, fy: M2.y, ax: 900, ay: 500 },
    { f: t.m2Done + 12, s: 1.7, fx: M2.x + 40, fy: M2.y, ax: 900, ay: 500 },
    { f: t.g1 - 4, s: 1.7, fx: G1.x, fy: G1.y - 20, ax: 960, ay: 500 },
    { f: t.g1Done + 12, s: 1.7, fx: G1.x, fy: G1.y - 20, ax: 960, ay: 500 },
    { f: t.m3 - 2, s: 1.2, fx: M3.x + M3.w / 2, fy: M3.y + M3.h / 2 - 20, ax: 960, ay: 540 },
    { f: t.resolve - 6, s: 1.2, fx: M3.x + M3.w / 2, fy: M3.y + M3.h / 2 - 20, ax: 960, ay: 540 },
    { f: t.resolve + 14, ...IDENTITY },
  ];
  const k = camAt(frame < t.page ? chatCam : pageCam, frame);
  const head = headlineAt(frame);
  const cta = prog(frame, t.m2, t.m2Done - t.m2, ease.outStrong);
  const mug = Math.min(1, popScale(frame, t.g1 + 4));

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />

      {/* the agent's side */}
      {chatOut < 1 ? (
        <AbsoluteFill style={{ opacity: 1 - chatOut, transform: `scale(${1 + chatOut * 0.15})` }}>
          <Camera keys={chatCam}>
            <ChatWindow composer={<Composer empty>{copy.chat.placeholder}</Composer>}>
              <Row at={-100} h={60} align="end">
                <UserBubble>{copy.chat.userAsk}</UserBubble>
              </Row>
              <Row at={-100} h={68}>
                <InlineReviewCard compact subtitle="Review · Pass 1" status={<SignalTag tone="attention">Changes requested</SignalTag>} />
              </Row>
              <Row at={t.notify} h={40} sfx>
                <Notice />
              </Row>
              <Row at={t.get} h={150}>
                <ToolCall tool={copy.fix.getTool} start={t.get} done={t.getDone}>
                  <Packet start={t.packet} />
                </ToolCall>
              </Row>
              <Row at={t.plan} h={33 + 4 * 46 + 8}>
                <AgentText>
                  <Streamed text="Here’s the plan, mark by mark:" start={t.plan} />
                  <Plan start={t.plan + 10} />
                </AgentText>
              </Row>
            </ChatWindow>
          </Camera>
        </AbsoluteFill>
      ) : null}

      {/* pass 2: the fixes land on the page */}
      {frame >= t.page - 2 ? (
        <AbsoluteFill style={{ opacity: pageIn, transform: `scale(${lerp(0.94, 1, pageIn)})` }}>
          <Camera keys={pageCam}>
            <UrlBar note={false} />
            <ReviewPage
              header={<ReviewHeader pass="Pass 2" />}
              site={{ headline: head.text, caret: head.caret, cta, mugLogo: mug }}
              overlay={
                <>
                  <RectMark {...M1} label="M1" popAt={-10} faded={frame >= t.m1Done ? 0.5 : 0} />
                  <FixTag x={M1.x + 24} y={M1.y - 18} status={statusOf(frame, t.m1, t.m1Done)} />
                  <PointMark x={M2.x} y={M2.y} label="M2" popAt={-10} faded={frame >= t.m2Done ? 0.5 : 0} />
                  <FixTag x={M2.x + 250} y={M2.y - 14} status={statusOf(frame, t.m2, t.m2Done)} />
                  <PointMark x={G1.x} y={G1.y + 60} label="M4" kind="guest" popAt={-10} faded={frame >= t.g1Done ? 0.5 : 0} />
                  <FixTag x={G1.x + 26} y={G1.y + 32} status={statusOf(frame, t.g1, t.g1Done)} />
                  <RectMark {...M3} label="M3" popAt={-10} />
                  <KeptTag x={M3.x + 24} y={M3.y - 18} at={t.m3 + 6} />
                  <ResolveChip at={t.resolve} done={t.resolveDone} />
                </>
              }
            />
          </Camera>
        </AbsoluteFill>
      ) : null}

      <StepRail step="Fix" />
      <VoLine id="v9" at={t.v9} />

      <Sfx at={t.m1Done} name="tick" volume={0.45} />
      <Sfx at={t.m2 + 2} name="pop" volume={0.4} />
      <Sfx at={t.m2Done} name="tick" volume={0.45} />
      <Sfx at={t.g1 + 4} name="pop" volume={0.45} />
      <Sfx at={t.g1Done} name="tick" volume={0.45} />
      <Sfx at={t.page - 2} name="whoosh" volume={0.4} />
    </AbsoluteFill>
  );
};

const Notice: React.FC = () => (
  <div style={{ display: 'flex', width: '100%', justifyContent: 'center' }}>
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 10,
        background: c.attentionSoft,
        color: c.attentionInk,
        borderRadius: 999,
        padding: '7px 16px 7px 8px',
        fontSize: 17,
        fontWeight: 500,
      }}
    >
      <AppIcon size={24} style={{ borderRadius: 999 }} />
      {copy.fix.notified}
    </span>
  </div>
);

const Packet: React.FC<{ start: number }> = ({ start }) => {
  const frame = useCurrentFrame();
  const lines: [string, React.ReactNode][] = [
    ['status', <span style={{ color: c.attentionInk }}>"changes_requested"</span>],
    ['next_action', <span style={{ background: c.highlight, color: c.fg, borderRadius: 4, padding: '0 4px' }}>"{copy.fix.nextAction}"</span>],
    ['marks', <span style={{ color: c.n700 }}>M1 must_fix · M2 nit · M3 keep · M4 nit</span>],
  ];
  return (
    <div style={{ marginTop: 12, marginLeft: 32, fontFamily: font.mono, fontSize: 16, lineHeight: '26px', display: 'flex', flexDirection: 'column' }}>
      {lines.map(([key, val], i) => (
        <div key={key} style={{ opacity: prog(frame, start + i * 7, 8), transform: `translateX(${lerp(-8, 0, prog(frame, start + i * 7, 8))}px)` }}>
          <span style={{ color: c.muted }}>{key}: </span>
          {val}
          <Sfx at={start + i * 7} name="tick" volume={0.25} />
        </div>
      ))}
    </div>
  );
};

/** The agent's plan, one row per mark, with the intent it read from each. */
const Plan: React.FC<{ start: number }> = ({ start }) => {
  const frame = useCurrentFrame();
  return (
    <div style={{ marginTop: 10, display: 'flex', flexDirection: 'column', gap: 8 }}>
      {copy.fix.plan.map((p, i) => {
        const keep = p.intent === 'Keep this';
        return (
          <div key={p.id} style={{ height: 38, display: 'flex', alignItems: 'center', gap: 12, fontSize: 19, ...appear(frame, start + i * 9, 10, 6) }}>
            <MarkBadge label={p.id} kind={p.kind === 'guest' ? 'guest' : 'mark'} size={28} />
            <span style={{ color: keep ? c.muted : c.fg }}>{p.text}</span>
            <div style={{ flex: 1 }} />
            <span
              style={{
                fontSize: 14,
                fontWeight: 500,
                padding: '3px 10px',
                borderRadius: 999,
                background: keep ? c.doneSoft : p.intent === 'Must fix' ? c.key : c.chip,
                color: keep ? c.doneInk : p.intent === 'Must fix' ? c.keyInk : c.n700,
              }}
            >
              {p.intent}
            </span>
            <Sfx at={start + i * 9} name="tick" volume={0.25} />
          </div>
        );
      })}
    </div>
  );
};

/** The status a fix is in, pinned beside its mark. */
const FixTag: React.FC<{ x: number; y: number; status: string }> = ({ x, y, status }) => {
  if (status === 'Open') return null;
  return (
    <div style={{ position: 'absolute', left: x, top: y, zIndex: 7, transform: 'scale(1.15)', transformOrigin: 'left center' }}>
      <SignalTag tone={status === 'Resolved' ? 'attention' : 'agent'}>{status}</SignalTag>
    </div>
  );
};

/** M3 stays exactly as it was. */
const KeptTag: React.FC<{ x: number; y: number; at: number }> = ({ x, y, at: start }) => {
  const frame = useCurrentFrame();
  if (frame < start) return null;
  return (
    <div style={{ position: 'absolute', left: x, top: y, zIndex: 7, transform: `scale(${1.15 * Math.min(1, popScale(frame, start))})`, transformOrigin: 'left center' }}>
      <SignalTag tone="done">Keep this · left as is</SignalTag>
    </div>
  );
};

/** resolve_marks, floating over the page once the fixes are in. */
const ResolveChip: React.FC<{ at: number; done: number }> = ({ at: start, done }) => {
  const frame = useCurrentFrame();
  if (frame < start) return null;
  return (
    <div style={{ position: 'absolute', left: 960, top: 880, transform: 'translateX(-50%)', width: 760, zIndex: 20, ...appear(frame, start, 12, 16) }}>
      <div style={{ background: c.raised, borderRadius: 18, boxShadow: shadow.window }}>
        <ToolCall tool={copy.fix.resolveTool} args={copy.fix.resolveArgs} start={start} done={done} />
      </div>
    </div>
  );
};
