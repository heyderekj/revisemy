import React from 'react';
import { AbsoluteFill, interpolate } from 'remotion';
import type { TransitionPresentation, TransitionPresentationComponentProps } from '@remotion/transitions';
import { ease } from '../theme';

type ZoomProps = { origin: string; direction: 'in' | 'out' };

const Zoom: React.FC<TransitionPresentationComponentProps<ZoomProps>> = ({
  children,
  presentationDirection,
  presentationProgress: p,
  passedProps: { origin, direction },
}) => {
  const e = ease.inOutStrong(p);
  const entering = presentationDirection === 'entering';
  let scale: number;
  let opacity: number;
  let blur: number;
  if (direction === 'in') {
    scale = entering ? interpolate(e, [0, 1], [0.86, 1]) : interpolate(e, [0, 1], [1, 2.6]);
    opacity = entering ? interpolate(p, [0.25, 0.75], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) : 1;
    blur = entering ? interpolate(p, [0.2, 0.8], [8, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) : interpolate(p, [0, 0.7], [0, 10], { extrapolateRight: 'clamp' });
  } else {
    scale = entering ? interpolate(e, [0, 1], [1.7, 1]) : interpolate(e, [0, 1], [1, 0.8]);
    opacity = entering ? interpolate(p, [0.2, 0.7], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) : 1;
    blur = entering ? interpolate(p, [0.2, 0.8], [8, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) : interpolate(p, [0.2, 0.9], [0, 8], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
  }
  return (
    <AbsoluteFill style={{ transform: `scale(${scale})`, transformOrigin: origin, opacity, filter: blur > 0.05 ? `blur(${blur}px)` : undefined }}>
      {children}
    </AbsoluteFill>
  );
};

export const zoomThrough = (props: ZoomProps): TransitionPresentation<ZoomProps> => ({ component: Zoom, props });

type LiftProps = { distance: number };

const Lift: React.FC<TransitionPresentationComponentProps<LiftProps>> = ({
  children,
  presentationDirection,
  presentationProgress: p,
  passedProps: { distance },
}) => {
  const e = ease.inOutStrong(p);
  const entering = presentationDirection === 'entering';
  const y = entering ? interpolate(e, [0, 1], [distance, 0]) : interpolate(e, [0, 1], [0, -distance * 0.6]);
  const opacity = entering ? interpolate(p, [0, 0.6], [0, 1], { extrapolateRight: 'clamp' }) : interpolate(p, [0.3, 1], [1, 0], { extrapolateLeft: 'clamp' });
  const scale = entering ? interpolate(e, [0, 1], [0.96, 1]) : interpolate(e, [0, 1], [1, 0.96]);
  return <AbsoluteFill style={{ transform: `translateY(${y}px) scale(${scale})`, opacity }}>{children}</AbsoluteFill>;
};

export const lift = (distance = 160): TransitionPresentation<LiftProps> => ({ component: Lift, props: { distance } });
