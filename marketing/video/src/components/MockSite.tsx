import React from 'react';
import { interpolateColors } from 'remotion';
import { lerp } from '../lib/anim';

/**
 * A fictional client site — "Northwind Studio" — drawn at 1280×800 and scaled
 * to whatever box it sits in. `fix` runs 0→1 from the first pass to the
 * second: looser headline, new CTA copy, darker button, warmer photo.
 */
export const SITE_W = 1280;
export const SITE_H = 800;

// Where the reviewable bits sit, as fractions of the site, for marks.
export const spots = {
  headline: { x: 0.036, y: 0.215, w: 0.47, h: 0.3 },
  cta: { x: 0.226, y: 0.676 },
  ctaHint: { x: 0.046, y: 0.76 },
  photo: { x: 0.77, y: 0.34 },
};

const ink = '#1b1d22';

export const MockSite: React.FC<{ width: number; fix?: number; ctaFix?: number; mobile?: boolean }> = ({
  width,
  fix = 0,
  ctaFix,
  mobile = false,
}) => {
  const cf = ctaFix ?? fix;
  if (mobile) return <MockMobile width={width} fix={fix} ctaFix={cf} />;
  const s = width / SITE_W;
  const btn = interpolateColors(fix, [0, 1], ['#f0a57a', '#b4532a']);
  const photoA = interpolateColors(fix, [0, 1], ['#9fb3c8', '#f2b47e']);
  const photoB = interpolateColors(fix, [0, 1], ['#55697f', '#c0603a']);
  const sun = interpolateColors(fix, [0, 1], ['#dfe8f1', '#ffe2b0']);
  const lh = lerp(0.88, 1.06, fix);
  const ls = lerp(-0.045, -0.025, fix);
  return (
    <div style={{ width, height: SITE_H * s, overflow: 'hidden', position: 'relative', background: '#fbf8f3' }}>
      <div
        style={{
          width: SITE_W,
          height: SITE_H,
          transform: `scale(${s})`,
          transformOrigin: '0 0',
          position: 'absolute',
          fontFamily: 'Figtree, sans-serif',
          color: ink,
        }}
      >
        {/* nav */}
        <div style={{ position: 'absolute', left: 56, right: 56, top: 0, height: 76, display: 'flex', alignItems: 'center', gap: 34 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontWeight: 700, fontSize: 22, letterSpacing: '-0.02em' }}>
            <span style={{ width: 26, height: 26, borderRadius: 7, background: ink, display: 'inline-block', position: 'relative' }}>
              <span style={{ position: 'absolute', left: 7, top: 7, width: 12, height: 12, borderRadius: 3, background: '#fbf8f3', transform: 'rotate(45deg)' }} />
            </span>
            Northwind
          </div>
          <div style={{ flex: 1 }} />
          {['Work', 'Studio', 'Journal', 'Contact'].map((l) => (
            <span key={l} style={{ fontSize: 17, color: '#4b4f58' }}>
              {l}
            </span>
          ))}
          <span style={{ fontSize: 16, fontWeight: 600, padding: '11px 18px', borderRadius: 999, border: `1.5px solid ${ink}` }}>Get in touch</span>
        </div>

        {/* hero copy */}
        <div style={{ position: 'absolute', left: 56, top: 140, fontSize: 15, fontWeight: 600, letterSpacing: '0.14em', textTransform: 'uppercase', color: '#8a6f55' }}>
          Brand &amp; web studio · Oslo
        </div>
        <div
          style={{
            position: 'absolute',
            left: 56,
            top: 186,
            width: 600,
            fontSize: 74,
            fontWeight: 700,
            lineHeight: lh,
            letterSpacing: `${ls}em`,
          }}
        >
          We build brands people remember.
        </div>
        <div style={{ position: 'absolute', left: 56, top: 448 + fix * 14, width: 520, fontSize: 21, lineHeight: 1.5, color: '#5b5f68' }}>
          Strategy, identity and websites for companies that would rather be loved than liked.
        </div>
        <div
          style={{
            position: 'absolute',
            left: 56,
            top: 540 + fix * 14,
            height: 58,
            padding: '0 30px',
            borderRadius: 999,
            background: btn,
            color: '#fff',
            display: 'flex',
            alignItems: 'center',
            fontSize: 19,
            fontWeight: 600,
          }}
        >
          <span style={{ position: 'relative' }}>
            <span style={{ opacity: 1 - cf }}>Start a project</span>
            <span style={{ position: 'absolute', left: 0, top: 0, opacity: cf, whiteSpace: 'nowrap' }}>Book a call</span>
          </span>
          <span style={{ marginLeft: 12 }}>→</span>
        </div>

        {/* photo */}
        <div
          style={{
            position: 'absolute',
            left: 708,
            top: 112,
            width: 516,
            height: 500,
            borderRadius: 22,
            overflow: 'hidden',
            background: `linear-gradient(160deg, ${photoA}, ${photoB})`,
          }}
        >
          <div style={{ position: 'absolute', left: 300, top: 70, width: 120, height: 120, borderRadius: '50%', background: sun, opacity: 0.9 }} />
          <div style={{ position: 'absolute', left: -60, top: 300, width: 420, height: 300, borderRadius: '50%', background: 'rgba(0,0,0,0.18)' }} />
          <div style={{ position: 'absolute', left: 180, top: 340, width: 460, height: 300, borderRadius: '50%', background: 'rgba(0,0,0,0.26)' }} />
          <div style={{ position: 'absolute', left: 120, top: 160, width: 150, height: 220, borderRadius: '75px 75px 12px 12px', background: 'rgba(255,255,255,0.22)' }} />
        </div>

        {/* trusted by */}
        <div style={{ position: 'absolute', left: 56, right: 56, top: 690, display: 'flex', alignItems: 'center', gap: 46 }}>
          <span style={{ fontSize: 14, color: '#8f9299', letterSpacing: '0.08em', textTransform: 'uppercase', fontWeight: 600 }}>Trusted by</span>
          {[120, 96, 140, 110, 128, 90].map((w, i) => (
            <span key={i} style={{ width: w, height: 22, borderRadius: 6, background: '#e6e0d6' }} />
          ))}
        </div>
      </div>
    </div>
  );
};

const MockMobile: React.FC<{ width: number; fix: number; ctaFix: number }> = ({ width, fix, ctaFix }) => {
  const W = 390;
  const H = 800;
  const s = width / W;
  const btn = interpolateColors(fix, [0, 1], ['#f0a57a', '#b4532a']);
  const photoA = interpolateColors(fix, [0, 1], ['#9fb3c8', '#f2b47e']);
  const photoB = interpolateColors(fix, [0, 1], ['#55697f', '#c0603a']);
  return (
    <div style={{ width, height: H * s, overflow: 'hidden', position: 'relative', background: '#fbf8f3' }}>
      <div style={{ width: W, height: H, transform: `scale(${s})`, transformOrigin: '0 0', position: 'absolute', fontFamily: 'Figtree, sans-serif', color: ink }}>
        <div style={{ position: 'absolute', left: 24, right: 24, top: 0, height: 64, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <span style={{ fontWeight: 700, fontSize: 20 }}>Northwind</span>
          <span style={{ width: 26, height: 3, background: ink, boxShadow: `0 8px 0 ${ink}` }} />
        </div>
        <div style={{ position: 'absolute', left: 24, top: 96, width: 340, fontSize: 46, fontWeight: 700, lineHeight: lerp(0.88, 1.06, fix), letterSpacing: '-0.04em' }}>
          We build brands people remember.
        </div>
        <div style={{ position: 'absolute', left: 24, top: 300, height: 52, padding: '0 24px', borderRadius: 999, background: btn, color: '#fff', display: 'flex', alignItems: 'center', fontSize: 17, fontWeight: 600 }}>
          {ctaFix > 0.5 ? 'Book a call' : 'Start a project'} →
        </div>
        <div style={{ position: 'absolute', left: 24, right: 24, top: 384, height: 360, borderRadius: 20, background: `linear-gradient(160deg, ${photoA}, ${photoB})` }} />
      </div>
    </div>
  );
};
