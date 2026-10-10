import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { AgentText, ChatWindow, Composer, InlineReviewCard, Row, ToolCall, UserBubble } from '../components/Chat';
import { Cursor, DotGrid, Streamed, Typed, typedEnd } from '../components/ui';
import { StepRail, VoLine } from '../components/Vo';
import { appear, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c } from '../theme';

// The site's hero chat (hero-loop-preview), played out.
export const ask = (() => {
  const vo = 14;
  const typeAt = 40;
  const typedTo = typedEnd(copy.chat.userAsk, typeAt, 'ask', 1.7);
  const send = Math.round(typedTo) + 9;
  const user = send + 1;
  const tool = send + 16;
  const steps = [tool + 16, tool + 30, tool + 44];
  const done = tool + 52;
  const reply = done + 8;
  const card = reply + 26;
  const click = card + 52;
  const end = click + 20;
  return { vo, typeAt, send, user, tool, steps, done, reply, card, click, end };
})();

export const ASK_DURATION = ask.end + 8;

// The card's Open pill once the thread has settled (thread bottom 876, card 368 tall).
const OPEN = { x: 1329, y: 542 };

export const Scene2Ask: React.FC = () => {
  const frame = useCurrentFrame();
  const t = ask;
  const typing = frame >= t.typeAt && frame < t.send;
  const sendPress = interpolate(frame - t.send, [0, 2, 7], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const pressOpen = interpolate(frame - t.click, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const cam = [
    { f: 0, ...IDENTITY },
    { f: 30, ...IDENTITY },
    { f: 50, s: 1.7, fx: 960, fy: 946, ax: 960, ay: 640 },
    { f: t.send, s: 1.7, fx: 960, fy: 946, ax: 960, ay: 640 },
    { f: t.send + 22, s: 1.45, fx: 960, fy: 790, ax: 960, ay: 540 },
    { f: t.card - 4, s: 1.45, fx: 960, fy: 760, ax: 960, ay: 520 },
    { f: t.card + 18, s: 1.15, fx: 960, fy: 640, ax: 960, ay: 520 },
    { f: t.click - 16, s: 1.15, fx: 960, fy: 640, ax: 960, ay: 520 },
    { f: t.click - 4, s: 1.55, fx: OPEN.x, fy: OPEN.y, ax: 1180, ay: 520 },
  ];
  const k = camAt(cam, frame);

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />
      <Camera keys={cam}>
        <ChatWindow
          style={appear(frame, 0, 18, 40)}
          composer={
            <Composer empty={!typing} sendPressed={sendPress} focused={prog(frame, 32, 6) * (frame < t.send ? 1 : 0)}>
              {typing ? <Typed text={copy.chat.userAsk} start={t.typeAt} seed="ask" speed={1.7} enter volume={0.32} /> : copy.chat.placeholder}
            </Composer>
          }
        >
          <EmptyState hideAt={t.send} />
          <Row at={t.user} h={60} align="end">
            <UserBubble>{copy.chat.userAsk}</UserBubble>
          </Row>
          <Row at={t.tool} h={188}>
            <ToolCall
              tool={copy.chat.createTool}
              args={copy.chat.createArgs}
              start={t.tool}
              done={t.done}
              steps={copy.chat.steps.map((label, i) => ({ label, at: t.steps[i] }))}
            />
          </Row>
          <Row at={t.reply} h={66}>
            <AgentText>
              <Streamed text={copy.chat.reviewReady} start={t.reply} wpf={0.8} />
            </AgentText>
          </Row>
          <Row at={t.card} h={368} sfx>
            <InlineReviewCard pressed={pressOpen} />
          </Row>
        </ChatWindow>

        <Cursor
          path={[
            { f: 14, x: 1460, y: 1040 },
            { f: 30, x: 640, y: 946 },
            { f: t.send + 6, x: 660, y: 950 },
            { f: t.send + 30, x: 1500, y: 760 },
            { f: t.card + 14, x: 1500, y: 760 },
            { f: t.click - 2, x: OPEN.x, y: OPEN.y },
          ]}
          clicks={[32, t.click]}
          appearAt={12}
        />
      </Camera>
      <StepRail step="Ask" enterAt={6} />
      <VoLine id="v2" at={t.vo} />
      <Sfx at={t.end - 4} name="whoosh" volume={0.6} />
    </AbsoluteFill>
  );
};

const EmptyState: React.FC<{ hideAt: number }> = ({ hideAt }) => {
  const frame = useCurrentFrame();
  const out = prog(frame, hideAt - 4, 10);
  if (out >= 1) return null;
  return (
    <div
      style={{
        position: 'absolute',
        left: 0,
        right: 0,
        top: 250,
        textAlign: 'center',
        fontSize: 44,
        fontWeight: 600,
        letterSpacing: '-0.02em',
        color: c.fg,
        ...appear(frame, 8, 16, 14),
        opacity: prog(frame, 8, 16) * (1 - out),
      }}
    >
      What should we look at today?
    </div>
  );
};
