import React from 'react';
import { c } from '../theme';

/**
 * Fieldnote Coffee's home page, the sample the website reviews
 * (resources/views/components/review-mock/samples/website.blade.php), drawn at
 * 1280×800 so 1cqw = 12.8px. It uses the theme tokens, so it turns over in
 * the dark cut the way the site's sample does.
 *
 * The props are the fixes the agent makes in pass 2:
 *  - headline / caret: the M1 rewrite, typed in place
 *  - cta: 0→1, the M2 button going from a grey pill to a solid one
 *  - mugLogo: 0→1, the G1 logo landing on the mug
 */
export const SITE_W = 1280;
export const SITE_H = 800;

// Marks and hints from config/review-samples.php ('website'), as fractions of the shot.
export const FN = {
  m1: { x: 0.07, y: 0.17, w: 0.5, h: 0.23 },
  m2: { x: 0.215, y: 0.475 },
  m3: { x: 0.06, y: 0.63, w: 0.88, h: 0.34 },
  s1: { x: 0.84, y: 0.09 },
  s2: { x: 0.66, y: 0.36 },
  g1: { x: 0.742, y: 0.4 }, // on the mug, for the guest's note
};

export const HEADLINE_BEFORE = 'We roast coffee in Des Moines.';
export const HEADLINE_AFTER = 'Fresh beans at your door, every two weeks.';

const cq = (n: number) => n * 12.8;

const BEANS: [string, string, string, string][] = [
  ['Kochere', 'Peach · jasmine', c.attention, '$19'],
  ['Huila', 'Cherry · cocoa', c.done, '$17'],
  ['House', 'Caramel · nut', c.n700, '$15'],
];

export const FieldnoteSite: React.FC<{
  width: number;
  headline?: string;
  caret?: boolean;
  cta?: number;
  mugLogo?: number;
}> = ({ width, headline = HEADLINE_BEFORE, caret = false, cta = 0, mugLogo = 0 }) => {
  const s = width / SITE_W;
  return (
    <div style={{ width, height: SITE_H * s, overflow: 'hidden', position: 'relative', background: c.raised }}>
      <div
        style={{
          width: SITE_W,
          height: SITE_H,
          transform: `scale(${s})`,
          transformOrigin: '0 0',
          position: 'absolute',
          fontFamily: 'Figtree, sans-serif',
          color: c.fg,
        }}
      >
        {/* nav */}
        <div style={{ position: 'absolute', left: '6%', right: '6%', top: '5%', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: cq(0.8), fontSize: cq(1.9), fontWeight: 600, letterSpacing: '-0.025em' }}>
            <span style={{ width: cq(2.2), height: cq(2.2), borderRadius: '50%', background: c.attention }} />
            Fieldnote
          </span>
          <span style={{ display: 'flex', gap: cq(2.2), fontSize: cq(1.35), color: c.muted }}>
            {['Shop', 'Subscribe', 'Cafés', 'Journal', 'About'].map((l) => (
              <span key={l}>{l}</span>
            ))}
          </span>
        </div>

        {/* hero copy */}
        <div style={{ position: 'absolute', left: '9%', top: '20%', width: '46%' }}>
          <p style={{ margin: 0, fontSize: cq(1.2), fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.14em', color: c.attentionInk }}>
            Small-batch roasters
          </p>
          <p style={{ margin: `${cq(0.8)}px 0 0`, fontSize: cq(3.9), fontWeight: 600, lineHeight: 1.05, letterSpacing: '-0.025em', minHeight: cq(3.9) * 1.05 }}>
            {headline}
            {caret ? <span style={{ display: 'inline-block', width: 3, height: '0.9em', marginLeft: 3, verticalAlign: '-0.08em', background: c.fg }} /> : null}
          </p>
        </div>
        <p style={{ position: 'absolute', margin: 0, left: '9%', top: '43%', width: '44%', fontSize: cq(1.4), lineHeight: 1.375, color: c.muted }}>
          Fresh beans every two weeks, roasted the morning they ship.
        </p>
        <div style={{ position: 'absolute', left: '9%', top: '50%', display: 'flex', alignItems: 'center', gap: cq(1.6) }}>
          <span style={{ position: 'relative', display: 'inline-flex' }}>
            {/* before: a grey pill that gets lost next to the link */}
            <span
              style={{
                borderRadius: 999,
                background: c.border,
                padding: `${cq(0.9)}px ${cq(1.8)}px`,
                fontSize: cq(1.3),
                fontWeight: 500,
                color: c.n700,
                opacity: 1 - cta,
                whiteSpace: 'nowrap',
              }}
            >
              Start a subscription
            </span>
            {/* after: solid, heavier, a touch larger */}
            <span
              style={{
                position: 'absolute',
                left: 0,
                top: '50%',
                transform: `translateY(-50%) scale(${0.96 + cta * 0.04})`,
                transformOrigin: 'left center',
                borderRadius: 999,
                background: c.fg,
                padding: `${cq(1.1)}px ${cq(2.1)}px`,
                fontSize: cq(1.35),
                fontWeight: 600,
                color: c.onFg,
                opacity: cta,
                whiteSpace: 'nowrap',
                boxShadow: '0 6px 16px -8px rgba(0,0,0,0.45)',
              }}
            >
              Start a subscription →
            </span>
          </span>
          <span
            style={{
              fontSize: cq(1.3),
              fontWeight: 500,
              textDecoration: 'underline',
              textUnderlineOffset: 2,
              marginLeft: cta * cq(1.6),
            }}
          >
            Browse beans
          </span>
        </div>

        {/* hero photo: a mug on a saucer, beans on the table */}
        <div
          style={{
            position: 'absolute',
            left: '60%',
            top: '16%',
            height: '42%',
            width: '34%',
            overflow: 'hidden',
            borderRadius: cq(1.2),
            background: c.attentionSoft,
          }}
        >
          <svg viewBox="0 0 120 90" preserveAspectRatio="xMidYMid slice" style={{ width: '100%', height: '100%', display: 'block' }}>
            <rect y="60" width="120" height="30" style={{ fill: c.attention, fillOpacity: 0.25 }} />
            <path
              d="M40 22c-3-4 3-7 0-11M50 20c-3-4 3-7 0-11M60 22c-3-4 3-7 0-11"
              style={{ stroke: c.attentionInk, strokeOpacity: 0.4 }}
              strokeWidth="1.6"
              fill="none"
              strokeLinecap="round"
            />
            <ellipse cx="50" cy="66" rx="32" ry="6.5" style={{ fill: c.card }} />
            <ellipse cx="50" cy="65" rx="22" ry="3.5" style={{ fill: '#000', fillOpacity: 0.05 }} />
            <path d="M30 32h40v22a10 10 0 0 1-10 10H40a10 10 0 0 1-10-10Z" style={{ fill: c.card }} />
            <path d="M70 37h4a7 7 0 0 1 0 14h-4" style={{ stroke: c.card }} strokeWidth="3.2" fill="none" />
            <ellipse cx="50" cy="32" rx="20" ry="3.6" style={{ fill: c.attentionInk }} />
            {/* G1: Fieldnote's dot on the mug */}
            <g transform={`translate(50 47) scale(${mugLogo})`} opacity={Math.min(1, mugLogo * 1.5)}>
              <circle r="5.2" style={{ fill: c.attention }} />
              <text y="11.8" textAnchor="middle" fontSize="3.6" fontWeight="600" style={{ fill: c.attentionInk }}>
                FIELDNOTE
              </text>
            </g>
            {[
              [92, 70, 20],
              [101, 76, -30],
              [86, 80, 60],
              [106, 66, 10],
            ].map(([x, y, r]) => (
              <g key={`${x}-${y}`} transform={`translate(${x} ${y}) rotate(${r})`}>
                <ellipse rx="4.2" ry="2.8" style={{ fill: c.attentionInk }} />
                <path d="M-3.4 0q1.7-1 3.4 0t3.4 0" style={{ stroke: c.attentionSoft }} strokeWidth="0.7" fill="none" />
              </g>
            ))}
          </svg>
          <p style={{ position: 'absolute', margin: 0, bottom: '6%', left: '6%', fontSize: cq(1.15), color: c.muted }}>Kochere, washed. Light roast.</p>
        </div>

        {/* product cards */}
        <div style={{ position: 'absolute', left: '8%', right: '8%', top: '66%', display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: cq(2) }}>
          {BEANS.map(([name, notes, bag, price]) => (
            <div key={name}>
              <div
                style={{
                  height: cq(11.5),
                  display: 'flex',
                  alignItems: 'flex-end',
                  justifyContent: 'center',
                  borderRadius: cq(1),
                  background: c.well,
                  paddingTop: cq(1.2),
                  boxSizing: 'border-box',
                }}
              >
                <Bag name={name.toUpperCase()} notes={notes} fill={bag} />
              </div>
              <div style={{ marginTop: cq(0.8), display: 'flex', justifyContent: 'space-between', fontSize: cq(1.3) }}>
                <span style={{ fontWeight: 500 }}>
                  {name}
                  {name === 'House' ? ' blend' : ''}
                </span>
                <span style={{ fontVariantNumeric: 'tabular-nums', color: c.muted }}>{price}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

/** bag.blade.php */
export const Bag: React.FC<{ name: string; notes: string; fill: string }> = ({ name, notes, fill }) => (
  <svg viewBox="0 0 60 80" style={{ height: '100%', width: 'auto', display: 'block' }}>
    <path d="M11 15h38l4 60a3 3 0 0 1-3 3H10a3 3 0 0 1-3-3Z" style={{ fill }} />
    <rect x="10" y="5" width="40" height="11" rx="1.5" style={{ fill }} />
    <rect x="10" y="5" width="40" height="11" rx="1.5" style={{ fill: '#000', fillOpacity: 0.15 }} />
    <path d="M14 8v5M19 8v5M24 8v5M29 8v5M34 8v5M39 8v5M44 8v5" style={{ stroke: '#000', strokeOpacity: 0.2 }} strokeWidth="0.8" />
    <rect x="14" y="30" width="32" height="32" rx="2" style={{ fill: c.card }} />
    <circle cx="30" cy="37" r="2.4" style={{ fill: c.attention }} />
    <text x="30" y="47" textAnchor="middle" fontSize="5.6" fontWeight="600" letterSpacing="0.2" style={{ fill: c.fg }}>
      {name}
    </text>
    <text x="30" y="54" textAnchor="middle" fontSize="3.4" style={{ fill: c.muted }}>
      {notes}
    </text>
  </svg>
);

/** A phone-width take on the same page, for the inline card's mobile capture. */
export const FieldnoteMobile: React.FC<{ width: number }> = ({ width }) => {
  const W = 390;
  const H = 800;
  const s = width / W;
  return (
    <div style={{ width, height: H * s, overflow: 'hidden', position: 'relative', background: c.raised }}>
      <div style={{ width: W, height: H, transform: `scale(${s})`, transformOrigin: '0 0', position: 'absolute', fontFamily: 'Figtree, sans-serif', color: c.fg }}>
        <div style={{ position: 'absolute', left: 24, right: 24, top: 22, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 21, fontWeight: 600 }}>
            <span style={{ width: 22, height: 22, borderRadius: '50%', background: c.attention }} />
            Fieldnote
          </span>
          <span style={{ width: 24, height: 2.5, background: c.fg, boxShadow: `0 7px 0 ${c.fg}` }} />
        </div>
        <div style={{ position: 'absolute', left: 24, top: 96, fontSize: 13, fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.14em', color: c.attentionInk }}>
          Small-batch roasters
        </div>
        <div style={{ position: 'absolute', left: 24, top: 122, width: 330, fontSize: 40, fontWeight: 600, lineHeight: 1.05, letterSpacing: '-0.025em' }}>
          We roast coffee in Des Moines.
        </div>
        <div style={{ position: 'absolute', left: 24, top: 236, width: 320, fontSize: 17, lineHeight: 1.375, color: c.muted }}>
          Fresh beans every two weeks, roasted the morning they ship.
        </div>
        <div style={{ position: 'absolute', left: 24, top: 304, borderRadius: 999, background: c.border, padding: '11px 20px', fontSize: 16, fontWeight: 500, color: c.n700 }}>
          Start a subscription
        </div>
        <div style={{ position: 'absolute', left: 24, right: 24, top: 376, height: 300, borderRadius: 16, background: c.attentionSoft }} />
      </div>
    </div>
  );
};
