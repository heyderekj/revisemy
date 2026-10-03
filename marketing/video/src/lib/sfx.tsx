import React from 'react';
import { Html5Audio, Sequence, staticFile } from 'remotion';

export type SfxName =
  | 'key-1' | 'key-2' | 'key-3' | 'key-4' | 'key-5' | 'key-6'
  | 'key-7' | 'key-8' | 'key-9' | 'key-10' | 'key-11' | 'key-12'
  | 'space-1' | 'space-2' | 'enter'
  | 'click' | 'click-down' | 'click-up'
  | 'whoosh' | 'swipe' | 'slide'
  | 'pop' | 'pop-high' | 'tick' | 'chime'
  | 'scribble' | 'drag' | 'stamp';

const LONG: Partial<Record<SfxName, number>> = { chime: 60, whoosh: 30, scribble: 24, stamp: 16, swipe: 14, slide: 12, drag: 18, enter: 10 };

/** One sound at a frame, relative to the enclosing Sequence. */
export const Sfx: React.FC<{ at: number; name: SfxName; volume?: number }> = ({ at, name, volume = 1 }) => (
  <Sequence from={Math.round(at)} durationInFrames={LONG[name] ?? 8} layout="none" name={`sfx:${name}`}>
    <Html5Audio src={staticFile(`sfx/${name}.wav`)} volume={volume} />
  </Sequence>
);
