import React from 'react';
import { Composition } from 'remotion';
import { scenes, Themed, TOTAL, Video } from './Video';
import type { Mode } from './theme';
import './theme';

export const RemotionRoot: React.FC = () => (
  <>
    <Composition id="ReviseMyPitch" component={Video} defaultProps={{ mode: 'light' as Mode }} durationInFrames={TOTAL} fps={30} width={1920} height={1080} />
    <Composition id="ReviseMyPitch-dark" component={Video} defaultProps={{ mode: 'dark' as Mode }} durationInFrames={TOTAL} fps={30} width={1920} height={1080} />
    {/* each scene on its own, for scrubbing and stills in local frames */}
    {scenes.map((s) => (
      <Composition
        key={s.id}
        id={`scene-${s.id}`}
        component={({ mode }: { mode: Mode }) => (
          <Themed mode={mode}>
            <s.C />
          </Themed>
        )}
        defaultProps={{ mode: 'light' as Mode }}
        durationInFrames={s.d}
        fps={30}
        width={1920}
        height={1080}
      />
    ))}
  </>
);
