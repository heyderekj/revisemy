import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { AgentText, ChatWindow, Composer, InlineReviewCard, Row, ToolCall, UserBubble } from '../components/Chat';
import { Cursor, DotGrid, Streamed, Typed, typedEnd } from '../components/ui';
import { appear, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c } from '../theme';

export const ask = (() => {
  const typeAt = 38;
  const typedTo = typedEnd(copy.chat.userAsk, typeAt, 'ask', 1.7);
  const send = Math.round(typedTo) + 9;
  const user = send + 1;
  const ack = send + 14;
  const tool = send + 30;
  const steps = [tool + 18, tool + 34, tool + 50];
  const done = tool + 58;
  const ready = done + 10;
  const card = ready + 16;
  const click = card + 56;
  const end = click + 22;
  return { typeAt, send, user, ack, tool, steps, done, ready, card, click, end };
})();

export const ASK_DURATION = ask.end + 8;

export const Scene2Ask: React.FC = () => {
  const frame = useCurrentFrame();
  const t = ask;
  const typing = frame >= t.typeAt && frame < t.send;
  const sendPress = interpolate(frame - t.send, [0, 2, 7], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const pressOpen = interpolate(frame - t.click, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const cam = [
    { f: 0, ...IDENTITY },
    { f: 30, ...IDENTITY },
    { f: 52, s: 1.75, fx: 960, fy: 946, ax: 960, ay: 640 },
    { f: t.send, s: 1.75, fx: 960, fy: 946, ax: 960, ay: 640 },
    { f: t.send + 24, s: 1.5, fx: 960, fy: 820, ax: 960, ay: 560 },
    { f: t.card - 4, s: 1.5, fx: 960, fy: 800, ax: 960, ay: 540 },
    { f: t.card + 18, s: 1.2, fx: 960, fy: 650, ax: 960, ay: 540 },
    { f: t.click - 16, s: 1.2, fx: 960, fy: 650, ax: 960, ay: 540 },
    { f: t.click - 4, s: 1.55, fx: 1300, fy: 846, ax: 1200, ay: 700 },
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
            {typing ? (
              <Typed text={copy.chat.userAsk} start={t.typeAt} seed="ask" speed={1.7} enter volume={0.32} />
            ) : (
              copy.chat.placeholder
            )}
          </Composer>
        }
      >
        <EmptyState hideAt={t.send} />
        <Row at={t.user} h={94} align="end">
          <UserBubble>{copy.chat.userAsk}</UserBubble>
        </Row>
        <Row at={t.ack} h={33}>
          <AgentText>
            <Streamed text={copy.chat.agentAck} start={t.ack} />
          </AgentText>
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
        <Row at={t.ready} h={66}>
          <AgentText avatar={false}>
            <Streamed text={copy.chat.reviewReady} start={t.ready} wpf={0.7} />
          </AgentText>
        </Row>
        <Row at={t.card} h={432} sfx>
          <InlineReviewCard pressed={pressOpen} />
        </Row>
      </ChatWindow>

      <Cursor
        path={[
          { f: 14, x: 1460, y: 1040 },
          { f: 30, x: 640, y: 946 },
          { f: t.send + 6, x: 660, y: 950 },
          { f: t.send + 30, x: 1500, y: 700 },
          { f: t.card + 14, x: 1500, y: 700 },
          { f: t.click - 2, x: 1300, y: 846 },
        ]}
        clicks={[32, t.click]}
        appearAt={12}
      />
      </Camera>
      <Sfx at={t.end - 4} name="whoosh" volume={0.7} />
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
