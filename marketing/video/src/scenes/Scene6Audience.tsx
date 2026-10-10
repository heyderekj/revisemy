import React from 'react';
import { AbsoluteFill, useCurrentFrame } from 'remotion';
import { Bag, FieldnoteMobile, FieldnoteSite } from '../components/FieldnoteSite';
import { DotGrid, MarkBadge } from '../components/ui';
import { VoLine } from '../components/Vo';
import { appear, popScale } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, font, shadow } from '../theme';

/*
 * What you can send for review, item by item. Each tile is a kind of work,
 * tagged with how ReviseMy captures it (a URL, an image, a PDF or HTML email),
 * and gets a mark so it reads as "and you mark it like this".
 */

type Item = { label: string; via: string; Thumb: React.FC; mark: { x: number; y: number } };

const ITEMS: Item[] = [
  { label: 'Landing pages', via: 'URL · desktop + mobile', Thumb: () => <SiteThumb />, mark: { x: 0.3, y: 0.32 } },
  { label: 'Product UI', via: 'Screenshot', Thumb: () => <DashboardThumb />, mark: { x: 0.62, y: 0.3 } },
  { label: 'Mobile layouts', via: 'URL · mobile', Thumb: () => <MobileThumb />, mark: { x: 0.56, y: 0.4 } },
  { label: 'Email newsletters', via: 'HTML email', Thumb: () => <EmailThumb />, mark: { x: 0.42, y: 0.78 } },
  { label: 'Pitch decks', via: 'PDF', Thumb: () => <SlideThumb />, mark: { x: 0.78, y: 0.36 } },
  { label: 'One-pagers', via: 'PDF', Thumb: () => <PageThumb />, mark: { x: 0.62, y: 0.42 } },
  { label: 'Social posts', via: 'Image', Thumb: () => <SocialThumb />, mark: { x: 0.68, y: 0.36 } },
  { label: 'Ads & banners', via: 'Image', Thumb: () => <BannerThumb />, mark: { x: 0.74, y: 0.5 } },
];

const COLS = 4;
const TILE = { w: 390, h: 318, gap: 26, thumb: 222 };
const GRID_X = (1920 - (COLS * TILE.w + (COLS - 1) * TILE.gap)) / 2;
const GRID_Y = 230;
const tileAt = (i: number) => 18 + i * 7;

export const AUDIENCE_DURATION = tileAt(ITEMS.length) + 96;

export const Scene6Audience: React.FC = () => {
  const frame = useCurrentFrame();
  return (
    <AbsoluteFill style={{ fontFamily: font.sans }}>
      <DotGrid />
      <div style={{ position: 'absolute', left: 0, right: 0, top: 92, textAlign: 'center', ...appear(frame, 0, 14, 14) }}>
        <div style={{ fontSize: 72, fontWeight: 600, letterSpacing: '-0.035em', color: c.fg }}>Review anything visual</div>
      </div>

      {ITEMS.map((it, i) => {
        const col = i % COLS;
        const row = Math.floor(i / COLS);
        const at = tileAt(i);
        const markAt = at + 12;
        return (
          <div
            key={it.label}
            style={{
              position: 'absolute',
              left: GRID_X + col * (TILE.w + TILE.gap),
              top: GRID_Y + row * (TILE.h + TILE.gap),
              width: TILE.w,
              height: TILE.h,
              borderRadius: 24,
              background: c.raised,
              boxShadow: shadow.window,
              overflow: 'hidden',
              ...appear(frame, at, 14, 24),
            }}
          >
            <div
              style={{
                position: 'relative',
                height: TILE.thumb,
                background: c.card,
                backgroundImage: `radial-gradient(circle at center, ${c.borderStrong} 1.2px, transparent 1.6px)`,
                backgroundSize: '16px 16px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
              }}
            >
              <it.Thumb />
              {frame >= markAt ? (
                <div style={{ position: 'absolute', left: `${it.mark.x * 100}%`, top: `${it.mark.y * 100}%`, transform: `translate(-50%, -50%) scale(${popScale(frame, markAt)})` }}>
                  <MarkBadge label="M1" size={34} style={{ boxShadow: `0 0 0 3px ${c.bg}, 0 3px 8px rgba(0,0,0,0.2)` }} />
                </div>
              ) : null}
            </div>
            <div style={{ padding: '18px 22px' }}>
              <div style={{ fontSize: 30, fontWeight: 600, letterSpacing: '-0.02em', color: c.fg }}>{it.label}</div>
              <div style={{ fontFamily: font.mono, fontSize: 17, color: c.muted, marginTop: 6 }}>{it.via}</div>
            </div>
            <Sfx at={at} name="tick" volume={0.25} />
            <Sfx at={markAt} name="pop" volume={0.25} />
          </div>
        );
      })}
      <VoLine id="v13" at={14} />
    </AbsoluteFill>
  );
};

/* ------------------------------------------------------------- thumbnails */

const frameStyle: React.CSSProperties = { borderRadius: 10, overflow: 'hidden', boxShadow: shadow.float, background: c.raised };
const bar = (w: number | string, h = 8, color = c.n150): React.CSSProperties => ({ width: w, height: h, borderRadius: h, background: color });

const SiteThumb: React.FC = () => (
  <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12 }}>
    <div style={frameStyle}>
      <FieldnoteSite width={250} />
    </div>
    <div style={{ ...frameStyle, borderRadius: 8 }}>
      <FieldnoteMobile width={62} />
    </div>
  </div>
);

const DashboardThumb: React.FC = () => (
  <div style={{ ...frameStyle, width: 300, height: 180, display: 'flex' }}>
    <div style={{ width: 62, background: c.well, padding: 10, display: 'flex', flexDirection: 'column', gap: 8 }}>
      {[0, 1, 2, 3].map((i) => (
        <div key={i} style={bar('100%', 7, i === 0 ? c.border : c.n150)} />
      ))}
    </div>
    <div style={{ flex: 1, padding: 12 }}>
      <div style={{ fontSize: 12, fontWeight: 600, color: c.fg }}>Ledgerly</div>
      <div style={{ display: 'flex', gap: 8, marginTop: 10 }}>
        {['$12.4k', '$3.1k', '$860'].map((v, i) => (
          <div key={v} style={{ flex: i === 0 ? 1.4 : 1, borderRadius: 8, background: c.well, padding: 8 }}>
            <div style={{ fontSize: 8, color: c.muted }}>{['Balance', 'In', 'Out'][i]}</div>
            <div style={{ fontSize: i === 0 ? 16 : 12, fontWeight: 600, color: c.fg, fontVariantNumeric: 'tabular-nums' }}>{v}</div>
          </div>
        ))}
      </div>
      <div style={{ display: 'flex', alignItems: 'flex-end', gap: 6, height: 64, marginTop: 12 }}>
        {[30, 48, 36, 60, 44, 56, 40].map((h, i) => (
          <div key={i} style={{ flex: 1, height: h, borderRadius: 4, background: i === 5 ? c.attention : c.n150 }} />
        ))}
      </div>
    </div>
  </div>
);

const MobileThumb: React.FC = () => (
  <div style={{ display: 'flex', gap: 14, alignItems: 'center' }}>
    {[0, 1].map((i) => (
      <div key={i} style={{ ...frameStyle, borderRadius: 16, border: `3px solid ${c.fg}`, transform: `translateY(${i ? 14 : -6}px)` }}>
        <FieldnoteMobile width={92} />
      </div>
    ))}
  </div>
);

const EmailThumb: React.FC = () => (
  <div style={{ ...frameStyle, width: 290, height: 196, background: c.well, padding: 12, boxSizing: 'border-box' }}>
    <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
      <span style={{ width: 16, height: 16, borderRadius: '50%', background: c.attention }} />
      <span style={{ fontSize: 10, fontWeight: 600, color: c.fg }}>Fieldnote Coffee</span>
    </div>
    <div style={{ fontSize: 12, fontWeight: 600, color: c.fg, marginTop: 6 }}>October beans are here</div>
    <div style={{ borderRadius: 8, background: c.raised, marginTop: 8, overflow: 'hidden' }}>
      <div style={{ height: 62, background: c.attentionSoft, display: 'flex', alignItems: 'flex-end', justifyContent: 'center', gap: 8, paddingTop: 6, boxSizing: 'border-box' }}>
        {(
          [
            ['KOCHERE', c.attention],
            ['HUILA', c.done],
            ['HOUSE', c.n700],
          ] as const
        ).map(([n, f]) => (
          <div key={n} style={{ height: 54 }}>
            <Bag name={n} notes="" fill={f} />
          </div>
        ))}
      </div>
      <div style={{ padding: 8 }}>
        <div style={{ fontSize: 11, fontWeight: 600, color: c.fg }}>Three new coffees for fall</div>
        <div style={{ display: 'inline-block', marginTop: 6, borderRadius: 5, background: c.fg, color: c.onFg, fontSize: 9, fontWeight: 600, padding: '4px 8px' }}>Learn more</div>
      </div>
    </div>
  </div>
);

const SlideThumb: React.FC = () => (
  <div style={{ ...frameStyle, width: 300, height: 169, padding: 16, boxSizing: 'border-box', position: 'relative' }}>
    <div style={{ fontSize: 9, color: c.muted, letterSpacing: '0.1em', textTransform: 'uppercase' }}>Traction</div>
    <div style={{ fontSize: 18, fontWeight: 600, color: c.fg, letterSpacing: '-0.02em', marginTop: 4, width: 170, lineHeight: 1.1 }}>3× retention since March</div>
    <div style={{ position: 'absolute', right: 16, bottom: 16, display: 'flex', alignItems: 'flex-end', gap: 8, height: 90 }}>
      {[26, 38, 50, 84].map((h, i) => (
        <div key={i} style={{ width: 18, height: h, borderRadius: 4, background: i === 3 ? c.attention : c.n150 }} />
      ))}
    </div>
    <div style={{ position: 'absolute', left: 16, bottom: 14, ...bar(90, 5) }} />
  </div>
);

const PageThumb: React.FC = () => (
  <div style={{ ...frameStyle, width: 150, height: 196, padding: 14, boxSizing: 'border-box', display: 'flex', flexDirection: 'column', gap: 7 }}>
    <div style={{ fontSize: 11, fontWeight: 600, color: c.fg }}>Fieldnote Wholesale</div>
    <div style={{ height: 56, borderRadius: 6, background: c.attentionSoft, marginBottom: 4 }} />
    {[100, 92, 96, 70, 88, 60].map((w, i) => (
      <div key={i} style={bar(`${w}%`, 6)} />
    ))}
  </div>
);

const SocialThumb: React.FC = () => (
  <div style={{ ...frameStyle, width: 186, height: 186, background: c.attentionSoft, position: 'relative' }}>
    <div style={{ position: 'absolute', left: 14, top: 14, fontSize: 15, fontWeight: 700, color: c.attentionInk, width: 120, lineHeight: 1.1 }}>New: Huila, cherry and cocoa</div>
    <div style={{ position: 'absolute', right: 16, bottom: 10, height: 112 }}>
      <Bag name="HUILA" notes="Cherry · cocoa" fill={c.done} />
    </div>
  </div>
);

const BannerThumb: React.FC = () => (
  <div style={{ display: 'flex', flexDirection: 'column', gap: 12, alignItems: 'center' }}>
    <div style={{ ...frameStyle, width: 320, height: 64, background: c.fg, display: 'flex', alignItems: 'center', padding: '0 14px', gap: 10, boxSizing: 'border-box' }}>
      <span style={{ width: 22, height: 22, borderRadius: '50%', background: c.attention }} />
      <span style={{ fontSize: 14, fontWeight: 600, color: c.onFg, flex: 1 }}>Fresh beans every two weeks</span>
      <span style={{ fontSize: 11, fontWeight: 600, background: c.attention, color: c.attentionInk, borderRadius: 999, padding: '5px 10px' }}>Subscribe</span>
    </div>
    <div style={{ ...frameStyle, width: 150, height: 110, background: c.attentionSoft, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', padding: 10, boxSizing: 'border-box' }}>
      <span style={{ fontSize: 12, fontWeight: 700, color: c.attentionInk }}>Roasted Monday</span>
      <span style={{ alignSelf: 'flex-start', fontSize: 10, fontWeight: 600, background: c.fg, color: c.onFg, borderRadius: 999, padding: '4px 9px' }}>Shop</span>
    </div>
  </div>
);
