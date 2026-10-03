import React from 'react';
import { interpolate, useCurrentFrame } from 'remotion';
import { appear, lerp, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, font, shadow } from '../theme';
import { MockSite } from './MockSite';
import { AppIcon, Button, Chip, Icon, Spinner } from './ui';

export const CHAT = { x: 300, y: 70, w: 1320, h: 940, header: 72, composerH: 124, col: 860 };

/** Generic agent chat window, no vendor branding. */
export const ChatWindow: React.FC<{
  composer: React.ReactNode;
  children: React.ReactNode;
  style?: React.CSSProperties;
  x?: number;
  w?: number;
}> = ({ composer, children, style, x = CHAT.x, w = CHAT.w }) => (
  <div
    style={{
      position: 'absolute',
      left: x,
      top: CHAT.y,
      width: w,
      height: CHAT.h,
      background: c.bg,
      borderRadius: 30,
      boxShadow: shadow.window,
      overflow: 'hidden',
      fontFamily: font.sans,
      ...style,
    }}
  >
    <div
      style={{
        height: CHAT.header,
        display: 'flex',
        alignItems: 'center',
        padding: '0 26px',
        gap: 10,
        borderBottom: `1px solid ${c.n100}`,
      }}
    >
      {[0, 1, 2].map((i) => (
        <span key={i} style={{ width: 13, height: 13, borderRadius: '50%', background: c.chip }} />
      ))}
      <span style={{ marginLeft: 18, fontSize: 19, fontWeight: 600, color: c.fg }}>New chat</span>
      <div style={{ flex: 1 }} />
      <span
        style={{
          display: 'inline-flex',
          alignItems: 'center',
          gap: 9,
          padding: '6px 12px 6px 7px',
          borderRadius: 10,
          background: c.card,
          fontSize: 16,
          fontWeight: 500,
          color: c.n700,
        }}
      >
        <AppIcon size={24} style={{ borderRadius: 6 }} />
        ReviseMy
        <span style={{ fontFamily: font.mono, fontSize: 13, color: c.muted }}>MCP</span>
        <span style={{ width: 8, height: 8, borderRadius: '50%', background: c.done }} />
      </span>
    </div>
    <div
      style={{
        position: 'absolute',
        left: 0,
        right: 0,
        top: CHAT.header,
        bottom: CHAT.composerH,
        overflow: 'hidden',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'flex-end',
        alignItems: 'center',
        WebkitMaskImage: 'linear-gradient(to bottom, transparent 0, #000 40px)',
      }}
    >
      <div style={{ width: CHAT.col, display: 'flex', flexDirection: 'column', paddingBottom: 10 }}>{children}</div>
    </div>
    <div style={{ position: 'absolute', left: 0, right: 0, bottom: 30, display: 'flex', justifyContent: 'center' }}>{composer}</div>
  </div>
);

/** Thread rows grow in from zero height so older rows get pushed up smoothly. */
export const Row: React.FC<{ at: number; h: number; gap?: number; children: React.ReactNode; align?: 'start' | 'end'; sfx?: boolean }> = ({
  at,
  h,
  gap = 24,
  children,
  align = 'start',
  sfx = false,
}) => {
  const frame = useCurrentFrame();
  const p = prog(frame, at, 14);
  if (frame < at) return null;
  return (
    <div style={{ height: (h + gap) * p, flexShrink: 0, position: 'relative' }}>
      <div
        style={{
          position: 'absolute',
          left: 0,
          right: 0,
          bottom: 0,
          display: 'flex',
          justifyContent: align === 'end' ? 'flex-end' : 'flex-start',
          ...appear(frame, at + 2, 12, 16),
          transformOrigin: align === 'end' ? 'bottom right' : 'bottom left',
        }}
      >
        {children}
      </div>
      {sfx ? <Sfx at={at + 2} name="pop" volume={0.35} /> : null}
    </div>
  );
};

export const UserBubble: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div
    style={{
      maxWidth: 640,
      background: c.n100,
      borderRadius: 22,
      padding: '15px 20px',
      fontSize: 22,
      lineHeight: 1.45,
      color: c.fg,
    }}
  >
    {children}
  </div>
);

export const AgentAvatar: React.FC = () => (
  <span
    style={{
      width: 36,
      height: 36,
      borderRadius: '50%',
      background: c.fg,
      color: c.onFg,
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      flexShrink: 0,
    }}
  >
    <Icon name="spark" size={20} color={c.onFg} />
  </span>
);

export const AgentText: React.FC<{ children: React.ReactNode; avatar?: boolean }> = ({ children, avatar = true }) => (
  <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start', width: '100%' }}>
    {avatar ? <AgentAvatar /> : <span style={{ width: 36, flexShrink: 0 }} />}
    <div style={{ fontSize: 22, lineHeight: 1.5, color: c.fg, paddingTop: 3, flex: 1 }}>{children}</div>
  </div>
);

/** A running MCP tool call: shimmer while working, steps tick off, then a check. */
export const ToolCall: React.FC<{
  tool: string;
  args?: string;
  start: number;
  done: number;
  steps?: { label: string; at: number }[];
  children?: React.ReactNode;
}> = ({ tool, args, start, done, steps = [], children }) => {
  const frame = useCurrentFrame();
  const running = frame < done;
  const shimmerX = ((frame - start) * 14) % 700;
  return (
    <div style={{ display: 'flex', gap: 16, width: '100%' }}>
      <span style={{ width: 36, flexShrink: 0 }} />
      <div style={{ flex: 1, background: c.card, borderRadius: 16, padding: '14px 18px', overflow: 'hidden', position: 'relative' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <AppIcon size={22} style={{ borderRadius: 5 }} />
          <span style={{ fontFamily: font.mono, fontSize: 17, color: c.n800, fontWeight: 500 }}>
            <span style={{ color: c.muted }}>revisemy · </span>
            {tool}
          </span>
          <div style={{ flex: 1 }} />
          {running ? (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 15, color: c.muted }}>
              <Spinner size={18} /> Working
            </span>
          ) : (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 15, color: c.doneInk, transform: `scale(${interpolate(frame - done, [0, 4, 9], [0.8, 1.08, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })})` }}>
              <Icon name="check" size={18} color={c.done} /> Done
            </span>
          )}
        </div>
        {args ? (
          <div style={{ fontFamily: font.mono, fontSize: 15, color: c.muted, marginTop: 8, marginLeft: 32 }}>{args}</div>
        ) : null}
        {steps.length ? (
          <div style={{ marginTop: 12, marginLeft: 32, display: 'flex', flexDirection: 'column', gap: 8 }}>
            {steps.map((s, i) => {
              const shown = frame >= s.at - 14;
              const ok = frame >= s.at;
              return (
                <div key={s.label} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 17, color: ok ? c.n800 : c.muted, opacity: shown ? prog(frame, s.at - 14, 8) : 0 }}>
                  {ok ? <Icon name="check" size={17} color={c.done} /> : <Spinner size={16} />}
                  {s.label}
                  {ok ? <Sfx at={s.at} name="tick" volume={0.5} /> : null}
                  {i === steps.length - 1 && ok ? null : null}
                </div>
              );
            })}
          </div>
        ) : null}
        {children}
        {running ? (
          <div
            style={{
              position: 'absolute',
              top: 0,
              bottom: 0,
              left: shimmerX - 200,
              width: 200,
              background: `linear-gradient(90deg, transparent, ${c.shimmer}, transparent)`,
              pointerEvents: 'none',
            }}
          />
        ) : null}
        {frame >= done ? <Sfx at={done} name="pop-high" volume={0.4} /> : null}
      </div>
    </div>
  );
};

/** The inline review the MCP app shows in chat. */
export const InlineReviewCard: React.FC<{
  pressed?: number;
  status?: React.ReactNode;
  compact?: boolean;
  fix?: number;
}> = ({ pressed = 0, status, compact = false, fix = 0 }) => (
  <div style={{ display: 'flex', gap: 16, width: '100%' }}>
    <span style={{ width: 36, flexShrink: 0 }} />
    <div style={{ flex: 1, background: c.raised, borderRadius: 18, boxShadow: shadow.float, overflow: 'hidden' }}>
      <div style={{ height: 56, display: 'flex', alignItems: 'center', gap: 10, padding: '0 16px', borderBottom: `1px solid ${c.n100}` }}>
        <AppIcon size={26} style={{ borderRadius: 6 }} />
        <span style={{ fontWeight: 600, fontSize: 19, color: c.fg }}>Northwind homepage</span>
        <Chip>Pass 1</Chip>
        <div style={{ flex: 1 }} />
        {status ?? <span style={{ fontSize: 15, color: c.muted }}>Inline review</span>}
      </div>
      {compact ? null : (
        <div
          style={{
            position: 'relative',
            background: c.card,
            backgroundImage: `radial-gradient(circle at center, ${c.borderStrong} 1.2px, transparent 1.6px)`,
            backgroundSize: '18px 18px',
            padding: '22px 26px 0',
            height: 310,
            display: 'flex',
            gap: 20,
            alignItems: 'flex-start',
            overflow: 'hidden',
          }}
        >
          <div style={{ borderRadius: '10px 10px 0 0', overflow: 'hidden', boxShadow: shadow.float }}>
            <MockSite width={560} fix={fix} />
          </div>
          <div style={{ borderRadius: 14, overflow: 'hidden', boxShadow: shadow.float, marginTop: 22 }}>
            <MockSite width={148} mobile fix={fix} />
          </div>
        </div>
      )}
      {compact ? null : (
        <div style={{ height: 66, display: 'flex', alignItems: 'center', padding: '0 16px', gap: 12 }}>
          <span style={{ fontFamily: font.mono, fontSize: 15, color: c.muted }}>revisemy.com/r/k7Q2xb</span>
          <div style={{ flex: 1 }} />
          <Button variant="primary" pressed={pressed}>
            Open review <Icon name="arrow" size={16} />
          </Button>
        </div>
      )}
    </div>
  </div>
);

export const Composer: React.FC<{ children?: React.ReactNode; empty?: boolean; sendPressed?: number; focused?: number }> = ({
  children,
  empty,
  sendPressed = 0,
  focused = 0,
}) => (
  <div
    style={{
      width: CHAT.col + 40,
      minHeight: 68,
      borderRadius: 22,
      background: c.card,
      boxShadow: `0 0 0 ${lerp(0, 2, focused)}px ${c.borderStrong}`,
      display: 'flex',
      alignItems: 'center',
      padding: '10px 12px 10px 24px',
      gap: 14,
      fontFamily: font.sans,
      boxSizing: 'border-box',
    }}
  >
    <div style={{ flex: 1, fontSize: 21, lineHeight: 1.4, color: empty ? c.n400 : c.fg }}>{children}</div>
    <span
      style={{
        width: 46,
        height: 46,
        borderRadius: '50%',
        background: empty ? c.chip : c.fg,
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
        transform: `scale(${1 - sendPressed * 0.12})`,
        flexShrink: 0,
      }}
    >
      <Icon name="send" size={22} color={empty ? c.n400 : c.onFg} />
    </span>
  </div>
);
