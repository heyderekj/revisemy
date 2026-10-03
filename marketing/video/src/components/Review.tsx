import React from 'react';
import { useCurrentFrame } from 'remotion';
import { appear, popScale, prog } from '../lib/anim';
import { c, font, shadow } from '../theme';
import { MockSite, spots } from './MockSite';
import { AppIcon, Button, Chip, Icon, MarkBadge, SignalTag, statusTone } from './ui';

// The review page as /r/{token} draws it, laid out for 1920×1080.
export const PAGE = { x: 70, y: 96, w: 1780, header: 68 };
export const CANVAS = { x: 90, y: PAGE.y + PAGE.header + 20, w: 1320 };
export const SITE = { x: CANVAS.x + 20, y: CANVAS.y + 20, w: 1280, h: 800 };
export const SIDE = { x: CANVAS.x + CANVAS.w + 20, y: CANVAS.y, w: 400 };

export const at = (fx: number, fy: number) => ({ x: SITE.x + fx * SITE.w, y: SITE.y + fy * SITE.h });
export const HEAD_RECT = {
  x: SITE.x + spots.headline.x * SITE.w,
  y: SITE.y + spots.headline.y * SITE.h,
  w: spots.headline.w * SITE.w,
  h: spots.headline.h * SITE.h,
};

// Header buttons, right-aligned, fixed widths so the cursor can find them.
const BTNS = [
  { id: 'share', label: 'Share', icon: 'link' as const, w: 112 },
  { id: 'board', label: 'Board', icon: 'board' as const, w: 116 },
  { id: 'changes', label: 'Changes', icon: 'undo' as const, w: 136 },
  { id: 'approve', label: 'Approve', icon: 'check' as const, w: 128 },
];
const BTN_GAP = 10;
export const btnCenter = (id: string) => {
  let right = PAGE.x + PAGE.w - 20;
  const list = [...BTNS].reverse();
  for (const b of list) {
    if (b.id === id) return { x: right - b.w / 2, y: PAGE.y + PAGE.header / 2 };
    right -= b.w + BTN_GAP;
  }
  return { x: 0, y: 0 };
};

export const ReviewHeader: React.FC<{ pass?: string; pressed?: Record<string, number>; active?: Record<string, boolean> }> = ({
  pass = 'Pass 1',
  pressed = {},
  active = {},
}) => (
  <div style={{ height: PAGE.header, display: 'flex', alignItems: 'center', gap: 14, padding: '0 20px', borderBottom: `1px solid ${c.border}` }}>
    <AppIcon size={34} style={{ borderRadius: 8 }} />
    <span style={{ fontSize: 24, fontWeight: 600, letterSpacing: '-0.02em', color: c.fg }}>Review</span>
    <Chip>{pass}</Chip>
    <Chip>
      <Icon name="link" size={14} color={c.n400} /> northwind.studio
    </Chip>
    <span style={{ fontSize: 17, color: c.muted }}>Northwind homepage</span>
    <div style={{ flex: 1 }} />
    {BTNS.map((b) => (
      <Button
        key={b.id}
        variant={b.id === 'approve' ? 'primary' : active[b.id] ? 'dark' : 'ghost'}
        pressed={pressed[b.id] ?? 0}
        style={{ width: b.w, justifyContent: 'center', padding: 0 }}
      >
        <Icon name={b.icon} size={16} />
        {b.label}
      </Button>
    ))}
  </div>
);

/** The page frame: header, canvas with the site, sidebar slot. Bleeds off the bottom like <x-review-mock>. */
export const ReviewPage: React.FC<{
  header: React.ReactNode;
  overlay?: React.ReactNode;
  sidebar?: React.ReactNode;
  fix?: number;
  style?: React.CSSProperties;
}> = ({ header, overlay, sidebar, fix = 0, style }) => (
  <div
    style={{
      position: 'absolute',
      left: PAGE.x,
      top: PAGE.y,
      width: PAGE.w,
      height: 1200,
      background: c.bg,
      borderRadius: '24px 24px 0 0',
      boxShadow: shadow.window,
      overflow: 'hidden',
      fontFamily: font.sans,
      ...style,
    }}
  >
    {header}
    <div style={{ position: 'absolute', left: CANVAS.x - PAGE.x, top: CANVAS.y - PAGE.y, width: CANVAS.w, height: 1100, background: c.card, borderRadius: 20 }}>
      <div style={{ position: 'absolute', left: 20, top: 20, borderRadius: 12, overflow: 'hidden', boxShadow: `0 0 0 1px ${c.ring}` }}>
        <MockSite width={SITE.w} fix={fix} />
      </div>
    </div>
    <div style={{ position: 'absolute', left: SIDE.x - PAGE.x, top: SIDE.y - PAGE.y, width: SIDE.w }}>{sidebar}</div>
    {/* overlay uses screen coordinates */}
    <div style={{ position: 'absolute', left: -PAGE.x, top: -PAGE.y, width: 1920, height: 1300 }}>{overlay}</div>
  </div>
);

export const Section: React.FC<{ title: string; count: number; children: React.ReactNode; style?: React.CSSProperties; right?: React.ReactNode }> = ({
  title,
  count,
  children,
  style,
  right,
}) => (
  <div style={{ background: c.card, borderRadius: 20, padding: '16px 16px 16px', ...style }}>
    <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
      <span style={{ fontSize: 19, fontWeight: 600, color: c.fg }}>{title}</span>
      <span style={{ borderRadius: 6, background: c.chip, padding: '1px 8px', fontSize: 14, fontWeight: 500, color: c.n600 }}>{count}</span>
      <div style={{ flex: 1 }} />
      {right}
    </div>
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>{children}</div>
  </div>
);

export const MarkCard: React.FC<{
  badge: React.ReactNode;
  label: string;
  status?: string;
  children: React.ReactNode;
  appearAt?: number;
  dashed?: boolean;
}> = ({ badge, label, status, children, appearAt, dashed }) => {
  const frame = useCurrentFrame();
  return (
    <div
      style={{
        background: dashed ? c.sky50 : c.raised,
        border: dashed ? `2px dashed ${c.sky200}` : undefined,
        borderRadius: 14,
        padding: 14,
        boxShadow: dashed ? undefined : shadow.raised,
        ...(appearAt === undefined ? {} : appear(frame, appearAt, 12, 12)),
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8 }}>
        {badge}
        <span style={{ fontSize: 15, color: c.muted }}>{label}</span>
        <div style={{ flex: 1 }} />
        {status ? <SignalTag tone={statusTone(status)}>{status}</SignalTag> : null}
      </div>
      <div style={{ fontSize: 18, lineHeight: 1.45, color: c.n700 }}>{children}</div>
    </div>
  );
};

/** Rectangle mark on the canvas. */
export const RectMark: React.FC<{ x: number; y: number; w: number; h: number; label: string; drawing?: boolean; popAt?: number; faded?: number }> = ({
  x,
  y,
  w,
  h,
  label,
  drawing,
  popAt,
  faded = 0,
}) => {
  const frame = useCurrentFrame();
  return (
    <div style={{ position: 'absolute', left: x, top: y, width: w, height: h, opacity: 1 - faded * 0.5 }}>
      <div
        style={{
          position: 'absolute',
          inset: 0,
          borderRadius: 8,
          border: `3px ${drawing ? 'dashed' : 'solid'} rgba(255,197,61,0.9)`,
          background: 'rgba(255,197,61,0.12)',
        }}
      />
      {popAt !== undefined && frame >= popAt ? (
        <div style={{ position: 'absolute', left: -15, top: -15, transform: `scale(${popScale(frame, popAt)})` }}>
          <MarkBadge label={label} size={34} style={{ boxShadow: `0 0 0 3px ${c.bg}, 0 2px 6px rgba(0,0,0,0.2)` }} />
        </div>
      ) : null}
    </div>
  );
};

/** Point mark / hint / guest badge, centred on (x, y). */
export const PointMark: React.FC<{ x: number; y: number; label: string; kind?: 'mark' | 'hint' | 'guest'; popAt: number; faded?: number }> = ({
  x,
  y,
  label,
  kind = 'mark',
  popAt,
  faded = 0,
}) => {
  const frame = useCurrentFrame();
  if (frame < popAt) return null;
  return (
    <div style={{ position: 'absolute', left: x, top: y, transform: `translate(-50%, -50%) scale(${popScale(frame, popAt)})`, opacity: 1 - faded * 0.5, zIndex: 5 }}>
      <MarkBadge label={label} kind={kind} size={36} style={{ boxShadow: `0 0 0 3px ${c.bg}, 0 3px 8px rgba(0,0,0,0.2)` }} />
    </div>
  );
};

export const Toast: React.FC<{ at: number; until: number; x: number; y: number; children: React.ReactNode; anchor?: 'center' | 'right' }> = ({
  at: start,
  until,
  x,
  y,
  children,
  anchor = 'center',
}) => {
  const frame = useCurrentFrame();
  if (frame < start || frame > until + 10) return null;
  const inP = prog(frame, start, 10);
  const outP = prog(frame, until, 10);
  return (
    <div
      style={{
        position: 'absolute',
        left: x,
        top: y,
        transform: `translate(${anchor === 'center' ? '-50%' : '-100%'}, ${(1 - inP) * 12 - outP * 8}px) scale(${0.96 + inP * 0.04})`,
        opacity: inP * (1 - outP),
        background: c.fg,
        color: c.onFg,
        borderRadius: 14,
        padding: '12px 18px',
        fontFamily: font.sans,
        fontSize: 18,
        fontWeight: 500,
        display: 'flex',
        alignItems: 'center',
        gap: 10,
        whiteSpace: 'nowrap',
        boxShadow: shadow.float,
        zIndex: 50,
      }}
    >
      {children}
    </div>
  );
};

export { at as siteAt };
