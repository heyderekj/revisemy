import { linearTiming, TransitionSeries } from '@remotion/transitions';
import { fade } from '@remotion/transitions/fade';
import React from 'react';
import { AbsoluteFill } from 'remotion';
import { c, Mode, themeVars } from './theme';
import { HOOK_DURATION, Scene1Hook } from './scenes/Scene1Hook';
import { ASK_DURATION, Scene2Ask } from './scenes/Scene2Ask';
import { MARK_DURATION, Scene3Mark } from './scenes/Scene3Mark';
import { FIX_DURATION, Scene4Fix } from './scenes/Scene4Fix';
import { Scene5Verify, VERIFY_DURATION } from './scenes/Scene5Verify';
import { AUDIENCE_DURATION, Scene6Audience } from './scenes/Scene6Audience';
import { CTA_DURATION, Scene7Cta } from './scenes/Scene7Cta';
import { lift, zoomThrough } from './lib/transitions';

// Ask → Mark → Fix → Verify, bookended by the hook and the audience/CTA.
// Each scene's length comes from its voiceover (src/vo/manifest.json).
export const scenes = [
  { id: 'hook', d: HOOK_DURATION, C: Scene1Hook },
  { id: 'ask', d: ASK_DURATION, C: Scene2Ask },
  { id: 'mark', d: MARK_DURATION, C: Scene3Mark },
  { id: 'fix', d: FIX_DURATION, C: Scene4Fix },
  { id: 'verify', d: VERIFY_DURATION, C: Scene5Verify },
  { id: 'audience', d: AUDIENCE_DURATION, C: Scene6Audience },
  { id: 'cta', d: CTA_DURATION, C: Scene7Cta },
];

const transitions = [
  { d: 18, p: fade() },
  { d: 26, p: zoomThrough({ origin: '61% 48%', direction: 'in' }) },
  { d: 26, p: zoomThrough({ origin: '35% 70%', direction: 'out' }) },
  { d: 14, p: fade() },
  { d: 22, p: lift(220) },
  { d: 20, p: fade() },
];

export const TOTAL =
  scenes.reduce((s, x) => s + x.d, 0) - transitions.slice(0, scenes.length - 1).reduce((s, x) => s + x.d, 0);

/** Sets the light or dark token values for everything inside. */
export const Themed: React.FC<{ mode: Mode; children: React.ReactNode }> = ({ mode, children }) => (
  <AbsoluteFill style={{ ...themeVars(mode), background: c.card }}>{children}</AbsoluteFill>
);

export const Video: React.FC<{ mode: Mode }> = ({ mode }) => (
  <Themed mode={mode}>
    <TransitionSeries>
      {scenes.map((s, i) => (
        <React.Fragment key={s.id}>
          {i > 0 ? (
            <TransitionSeries.Transition timing={linearTiming({ durationInFrames: transitions[i - 1].d })} presentation={transitions[i - 1].p as never} />
          ) : null}
          <TransitionSeries.Sequence durationInFrames={s.d} name={s.id}>
            <s.C />
          </TransitionSeries.Sequence>
        </React.Fragment>
      ))}
    </TransitionSeries>
  </Themed>
);
