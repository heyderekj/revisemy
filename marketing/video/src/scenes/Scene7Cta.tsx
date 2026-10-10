import React from 'react';
import { AbsoluteFill, interpolate, useCurrentFrame } from 'remotion';
import { copy } from '../copy';
import { AppIcon, Button, Cursor, DotGrid, Typed, typedEnd } from '../components/ui';
import { voFrames, VoLine } from '../components/Vo';
import { appear, lerp, popScale, prog } from '../lib/anim';
import { Sfx } from '../lib/sfx';
import { c, ease, font } from '../theme';

const VO_AT = 10;
export const CTA_DURATION = Math.max(240, VO_AT + voFrames('v14') + 70);

export const Scene7Cta: React.FC = () => {
  const frame = useCurrentFrame();
  const typeAt = 22;
  const typed = Math.round(typedEnd(copy.cta.tagline, typeAt, 'cta', 1.8));
  const sweep = prog(frame, typed + 4, 14, ease.snap);
  const btnAt = typed + 14;
  const click = btnAt + 34;
  const press = interpolate(frame - click, [0, 2, 8], [0, 1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  const clicked = frame >= click;

  const words = copy.cta.tagline.split(' ');
  return (
    <AbsoluteFill style={{ fontFamily: font.sans }}>
      <DotGrid />
      <div style={{ position: 'absolute', left: 0, right: 0, top: 290, display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 16 }}>
        <div style={{ transform: `scale(${popScale(frame, 2)})` }}>
          <AppIcon size={64} />
        </div>
        <span style={{ fontSize: 50, fontWeight: 600, letterSpacing: '-0.03em', color: c.fg, ...appear(frame, 8, 14, 0), transform: `translateX(${lerp(-20, 0, prog(frame, 8, 14))}px)` }}>
          ReviseMy
        </span>
        <Sfx at={2} name="pop" volume={0.6} />
      </div>

      <div style={{ position: 'absolute', left: 0, right: 0, top: 420, textAlign: 'center', fontSize: 104, fontWeight: 600, letterSpacing: '-0.04em', color: c.fg, whiteSpace: 'nowrap' }}>
        {/* the highlight sits behind "feedback" once it's typed */}
        <span style={{ position: 'relative', display: 'inline-block' }}>
          <span style={{ visibility: 'hidden' }}>
            {words[0]}{' '}
            <span style={{ position: 'relative' }}>
              <span
                style={{
                  visibility: 'visible',
                  position: 'absolute',
                  left: -6,
                  top: '56%',
                  height: '36%',
                  width: `calc(${sweep * 100}% + 12px)`,
                  background: c.marker,
                  borderRadius: 6,
                  opacity: sweep > 0 ? 0.9 : 0,
                }}
              />
              {words[1]}
            </span>{' '}
            {words.slice(2).join(' ')}
          </span>
          <span style={{ position: 'absolute', left: 0, top: 0, whiteSpace: 'nowrap', textAlign: 'left' }}>
            <Typed text={copy.cta.tagline} start={typeAt} seed="cta" speed={1.8} caretUntil={typed + 10} volume={0.32} />
          </span>
        </span>
      </div>

      <div style={{ position: 'absolute', left: 0, right: 0, top: 640, display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 26, ...appear(frame, btnAt, 14, 16) }}>
        <Button variant="primary" pressed={press} style={{ height: 74, fontSize: 30, padding: '0 34px', borderRadius: 18, gap: 12 }}>
          {clicked ? 'Token ready ✓' : 'Get a try token'}
        </Button>
        <span style={{ fontSize: 30, color: c.n600 }}>
          {copy.cta.free} at <span style={{ fontFamily: font.mono, color: c.fg }}>{copy.cta.url}</span>
        </span>
      </div>


      <Cursor
        path={[
          { f: btnAt + 6, x: 1200, y: 980 },
          { f: click - 2, x: 760, y: 678 },
          { f: click + 30, x: 820, y: 760 },
        ]}
        clicks={[click]}
        appearAt={btnAt + 6}
        hideAt={click + 30}
      />
      <Sfx at={click + 2} name="chime" volume={0.6} />
      <VoLine id="v14" at={VO_AT} captionY={900} />
    </AbsoluteFill>
  );
};
