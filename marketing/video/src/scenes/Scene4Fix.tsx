import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { Camera, camAt, IDENTITY } from '../components/Camera';
import { AgentText, ChatWindow, Composer, InlineReviewCard, Row, ToolCall, UserBubble } from '../components/Chat';
import { MockSite } from '../components/MockSite';
import { AppIcon, Button, Chip, Cursor, DotGrid, Icon, MarkBadge, SignalTag, Spinner, statusTone, Streamed } from '../components/ui';
import { appear, lerp, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font, shadow } from '../theme';

const WIN = { x: 110, w: 1080 };

export const fx = (() => {
  const notify = 18;
  const get = 32;
  const getDone = 56;
  const packet = 60;
  const working = 92;
  const list = 100;
  const items = [0, 1, 2, 3].map((i) => ({ prog: 110 + i * 24, done: 128 + i * 24 }));
  const wipeFrom = 132;
  const wipeTo = 204;
  const resolve = 214;
  const resolveDone = 238;
  const ready = 248;
  const click = 290;
  const end = click + 20;
  return { notify, get, getDone, packet, working, list, items, wipeFrom, wipeTo, resolve, resolveDone, ready, click, end };
})();

export const FIX_DURATION = fx.end + 20;

export const Scene4Fix: React.FC = () => {
  const frame = useCurrentFrame();
  const t = fx;
  const pressBoard = interpolate(frame - t.click, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });

  const btn = { x: 352, y: 852 };
  const cam = [
    { f: 0, s: 1.1, fx: 960, fy: 540 },
    { f: 22, s: 1.55, fx: 650, fy: 700, ax: 960, ay: 560 },
    { f: t.list + 20, s: 1.5, fx: 650, fy: 740, ax: 960, ay: 560 },
    { f: t.wipeFrom - 6, s: 1.8, fx: 1554, fy: 415, ax: 960, ay: 540 },
    { f: t.wipeTo + 4, s: 1.8, fx: 1554, fy: 415, ax: 960, ay: 540 },
    { f: t.resolve + 4, s: 1.5, fx: 650, fy: 760, ax: 960, ay: 560 },
    { f: t.ready + 8, s: 1.5, fx: 650, fy: 780, ax: 960, ay: 560 },
    { f: t.click - 6, s: 1.65, fx: btn.x + 120, fy: btn.y - 20, ax: 820, ay: 620 },
  ];
  const k = camAt(cam, frame);

  return (
    <AbsoluteFill>
      <DotGrid dx={(960 - k.fx) * 0.08} dy={(540 - k.fy) * 0.08} />
      <Camera keys={cam}>
        <ChatWindow x={WIN.x} w={WIN.w} composer={<Composer empty>{copy.chat.placeholder}</Composer>}>
          <Row at={-100} h={94} align="end">
            <UserBubble>{copy.chat.userAsk}</UserBubble>
          </Row>
          <Row at={-100} h={56}>
            <InlineReviewCard compact status={<SignalTag tone="attention">Changes requested</SignalTag>} />
          </Row>
          <Row at={t.notify} h={40} sfx>
            <Notice />
          </Row>
          <Row at={t.get} h={168}>
            <ToolCall tool={copy.fix.getTool} start={t.get} done={t.getDone}>
              <Packet start={t.packet} />
            </ToolCall>
          </Row>
          <Row at={t.working} h={33}>
            <AgentText>
              <Streamed text={copy.fix.working} start={t.working} />
            </AgentText>
          </Row>
          <Row at={t.list} h={4 * 46}>
            <Checklist />
          </Row>
          <Row at={t.resolve} h={88}>
            <ToolCall tool={copy.fix.resolveTool} args="marks: [M1, M2, S1, G1] · after_image ✓" start={t.resolve} done={t.resolveDone} />
          </Row>
          <Row at={t.ready} h={92}>
            <AgentText avatar={false}>
              <Streamed text={copy.fix.done} start={t.ready} />
              <div style={{ marginTop: 12, ...appear(frame, t.ready + 10, 10, 6) }}>
                <Button variant="primary" pressed={pressBoard}>
                  <Icon name="board" size={16} /> Open board
                </Button>
              </div>
            </AgentText>
          </Row>
        </ChatWindow>

        <BeforeAfter />

        <Cursor
          path={[
            { f: t.ready, x: 1500, y: 1040 },
            { f: t.click - 2, x: btn.x, y: btn.y },
          ]}
          clicks={[t.click]}
          appearAt={t.ready}
        />
      </Camera>
      <Sfx at={t.end - 6} name="whoosh" volume={0.5} />
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
    ['marks', <span style={{ color: c.n700 }}>M1 must_fix · M2 nit · S1 hint · G1 guest</span>],
  ];
  return (
    <div style={{ marginTop: 12, marginLeft: 32, fontFamily: font.mono, fontSize: 16, lineHeight: '26px', display: 'flex', flexDirection: 'column' }}>
      {lines.map(([key, val], i) => (
        <div key={key} style={{ opacity: prog(frame, start + i * 8, 8), transform: `translateX(${lerp(-8, 0, prog(frame, start + i * 8, 8))}px)` }}>
          <span style={{ color: c.muted }}>{key}: </span>
          {val}
          <Sfx at={start + i * 8} name="tick" volume={0.3} />
        </div>
      ))}
    </div>
  );
};

const Checklist: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <div style={{ display: 'flex', gap: 16, width: '100%' }}>
      <span style={{ width: 36, flexShrink: 0 }} />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 8 }}>
        {copy.fix.items.map((it, i) => {
          const tm = fx.items[i];
          const status = frame >= tm.done ? 'Resolved' : frame >= tm.prog ? 'In progress' : 'Open';
          const kind = it.id.startsWith('S') ? 'hint' : it.id.startsWith('G') ? 'guest' : 'mark';
          return (
            <div
              key={it.id}
              style={{
                height: 38,
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                fontSize: 19,
                color: status === 'Resolved' ? c.n600 : c.fg,
                ...appear(frame, fx.list + i * 3, 10, 6),
              }}
            >
              <MarkBadge label={it.id} kind={kind} size={28} />
              <span style={{ textDecoration: status === 'Resolved' ? 'line-through' : 'none', textDecorationColor: c.n400 }}>{it.text}</span>
              <div style={{ flex: 1 }} />
              {status === 'In progress' ? <Spinner size={16} color={c.sky500} /> : null}
              <SignalTag tone={statusTone(status)}>{status}</SignalTag>
              <Sfx at={tm.done} name="tick" volume={0.45} />
            </div>
          );
        })}
      </div>
    </div>
  );
};

const BeforeAfter: React.FC = () => {
  const frame = useCurrentFrame();
  const t = fx;
  const w = 600;
  const h = (w * 800) / 1280;
  const p = prog(frame, t.wipeFrom, t.wipeTo - t.wipeFrom, ease.inOutStrong);
  const x = p * w;
  return (
    <div
      style={{
        position: 'absolute',
        left: 1238,
        top: 190,
        width: w + 32,
        background: c.bg,
        borderRadius: 24,
        boxShadow: shadow.window,
        padding: 16,
        fontFamily: font.sans,
        ...appear(frame, t.wipeFrom - 16, 16, 30),
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 14 }}>
        <AppIcon size={26} style={{ borderRadius: 6 }} />
        <span style={{ fontSize: 19, fontWeight: 600, color: c.fg }}>Northwind homepage</span>
        <Chip>{frame >= t.resolveDone ? 'Pass 2' : 'Pass 1 → 2'}</Chip>
      </div>
      <div style={{ position: 'relative', width: w, height: h, borderRadius: 12, overflow: 'hidden', boxShadow: `0 0 0 1px ${c.ring}` }}>
        <MockSite width={w} fix={0} />
        <div style={{ position: 'absolute', inset: 0, clipPath: `inset(0 ${w - x}px 0 0)` }}>
          <MockSite width={w} fix={1} />
        </div>
        <div style={{ position: 'absolute', left: x - 1.5, top: 0, bottom: 0, width: 3, background: c.key, opacity: p > 0 && p < 1 ? 1 : 0 }} />
        <div
          style={{
            position: 'absolute',
            left: x - 20,
            top: h / 2 - 20,
            width: 40,
            height: 40,
            borderRadius: '50%',
            background: c.key,
            boxShadow: shadow.float,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: 18,
            fontWeight: 700,
            color: c.keyInk,
            opacity: p > 0 && p < 1 ? 1 : 0,
          }}
        >
          ↔
        </div>
        <span style={{ position: 'absolute', right: 12, top: 12, opacity: 1 - p }}>
          <Chip style={{ background: c.glass }}>Before</Chip>
        </span>
        <span style={{ position: 'absolute', left: 12, top: 12, opacity: p }}>
          <Chip style={{ background: c.key, color: c.keyInk }}>After</Chip>
        </span>
      </div>
      <div style={{ display: 'flex', gap: 8, marginTop: 14, flexWrap: 'wrap' }}>
        {copy.fix.items.map((it, i) => (
          <span key={it.id} style={{ opacity: frame >= fx.items[i].done ? 1 : 0.35 }}>
            <SignalTag tone={frame >= fx.items[i].done ? 'attention' : 'neutral'}>{it.id} {frame >= fx.items[i].done ? 'resolved' : 'open'}</SignalTag>
          </span>
        ))}
      </div>
      <Sfx at={t.wipeFrom} name="swipe" volume={0.5} />
    </div>
  );
};
