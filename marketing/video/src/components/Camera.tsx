import React from 'react';
import { useCurrentFrame } from 'remotion';
import { lerp } from '../lib/anim';
import { ease } from '../theme';

/** s = zoom, (fx, fy) = point of interest, (ax, ay) = where on screen it lands. */
export type CamKey = { f: number; s: number; fx: number; fy: number; ax?: number; ay?: number };

export const camAt = (keys: CamKey[], frame: number) => {
  const norm = (k: CamKey) => ({ ...k, ax: k.ax ?? 960, ay: k.ay ?? 540 });
  if (frame <= keys[0].f) return norm(keys[0]);
  for (let i = 0; i < keys.length - 1; i++) {
    const a = norm(keys[i]);
    const b = norm(keys[i + 1]);
    if (frame <= b.f) {
      const t = ease.inOutStrong((frame - a.f) / Math.max(1, b.f - a.f));
      return {
        f: frame,
        s: lerp(a.s, b.s, t),
        fx: lerp(a.fx, b.fx, t),
        fy: lerp(a.fy, b.fy, t),
        ax: lerp(a.ax, b.ax, t),
        ay: lerp(a.ay, b.ay, t),
      };
    }
  }
  return norm(keys[keys.length - 1]);
};

export const IDENTITY: Omit<CamKey, 'f'> = { s: 1, fx: 960, fy: 540, ax: 960, ay: 540 };

export const Camera: React.FC<{ keys: CamKey[]; children: React.ReactNode }> = ({ keys, children }) => {
  const frame = useCurrentFrame();
  const k = camAt(keys, frame);
  return (
    <div
      style={{
        position: 'absolute',
        left: 0,
        top: 0,
        width: 1920,
        height: 1080,
        transformOrigin: '0 0',
        transform: `translate(${k.ax - k.fx * k.s}px, ${k.ay - k.fy * k.s}px) scale(${k.s})`,
      }}
    >
      {children}
    </div>
  );
};
