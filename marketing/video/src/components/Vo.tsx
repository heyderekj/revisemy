import React from 'react';
import { Html5Audio, Sequence, staticFile, useCurrentFrame } from 'remotion';
import manifest from '../vo/manifest.json';
import { prog } from '../lib/anim';
import { c, font, shadow } from '../theme';

export type VoId = keyof typeof manifest.lines;
type Line = { text: string; frames: number; words: number[]; real: boolean };
const line = (id: VoId) => manifest.lines[id] as Line;

/**
 * Voiceover on or off. Off: no audio and no captions, and each line becomes a
 * shorter silent beat (SILENT of its spoken length) so the cut doesn't wait on
 * speech that isn't there. Turn it on once public/vo/*.mp3 are recorded.
 */
export const VO_ON = false;
const SILENT = 0.55;
const scale = (f: number) => (VO_ON ? f : Math.round(f * SILENT));

/** When word `i` of a line is spoken, in frames from the line's start. */
export const voWord = (id: VoId, word: string) => {
  const l = line(id);
  const i = l.text.split(/\s+/).findIndex((w) => w.toLowerCase().replace(/[^a-z]/g, '') === word);
  return scale(l.words[Math.max(0, i)] ?? 0);
};

/** How long a line runs, in frames. Scenes build their beats from these. */
export const voFrames = (id: VoId) => scale(line(id).frames);

/**
 * Lays lines end to end from `start`, each followed by its gap (frames).
 * Returns each line's start frame and the frame after the last gap.
 */
export const voSequence = (start: number, items: [VoId, number][]) => {
  const at: Partial<Record<VoId, number>> = {};
  let f = start;
  for (const [id, gap] of items) {
    at[id] = f;
    f += voFrames(id) + gap;
  }
  return { at: at as Record<VoId, number>, end: f };
};

/**
 * One line of voiceover: plays its audio (once recorded) and shows its caption
 * as a lower third, words arriving as they're spoken.
 */
export const VoLine: React.FC<{ id: VoId; at: number; captionY?: number }> = (props) => (VO_ON ? <SpokenLine {...props} /> : null);

const SpokenLine: React.FC<{ id: VoId; at: number; captionY?: number }> = ({ id, at, captionY = 978 }) => {
  const frame = useCurrentFrame();
  const l = line(id);
  const local = frame - at;
  const words = l.text.split(/\s+/);
  const inP = prog(frame, at - 4, 8);
  const outP = prog(frame, at + l.frames + 6, 10);
  const visible = local > -6 && local < l.frames + 18;
  return (
    <>
      {l.real ? (
        <Sequence from={at} durationInFrames={l.frames + 15} layout="none" name={`vo:${id}`}>
          <Html5Audio src={staticFile(`vo/${id}.mp3`)} />
        </Sequence>
      ) : null}
      {visible ? (
        <div
          style={{
            position: 'absolute',
            left: 0,
            right: 0,
            top: captionY,
            display: 'flex',
            justifyContent: 'center',
            zIndex: 200,
            opacity: inP * (1 - outP),
            transform: `translateY(${(1 - inP) * 8}px)`,
          }}
        >
          <div
            style={{
              maxWidth: 1360,
              background: c.glass,
              boxShadow: shadow.float,
              borderRadius: 18,
              padding: '12px 24px',
              fontFamily: font.sans,
              fontSize: 31,
              fontWeight: 500,
              lineHeight: 1.35,
              letterSpacing: '-0.01em',
              color: c.fg,
              textAlign: 'center',
              backdropFilter: 'blur(10px)',
            }}
          >
            {words.map((w, i) => {
              const p = prog(local, l.words[i] ?? 0, 5);
              return (
                <span key={i} style={{ opacity: 0.28 + p * 0.72 }}>
                  {w}
                  {i < words.length - 1 ? ' ' : ''}
                </span>
              );
            })}
          </div>
        </div>
      ) : null}
    </>
  );
};

const STEPS = ['Ask', 'Mark', 'Fix', 'Verify'] as const;
export type Step = (typeof STEPS)[number];

/** The four steps, bottom centre, with the current one lit. */
export const StepRail: React.FC<{ step: Step; enterAt?: number }> = ({ step, enterAt = 0 }) => {
  const frame = useCurrentFrame();
  const p = prog(frame, enterAt, 12);
  const current = STEPS.indexOf(step);
  return (
    <div
      style={{
        position: 'absolute',
        left: 0,
        right: 0,
        bottom: 30,
        display: 'flex',
        justifyContent: 'center',
        zIndex: 190,
        opacity: p,
        transform: `translateY(${(1 - p) * 12}px)`,
      }}
    >
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 10,
          padding: 8,
          borderRadius: 999,
          background: c.glass,
          boxShadow: shadow.float,
          fontFamily: font.sans,
          backdropFilter: 'blur(10px)',
        }}
      >
        {STEPS.map((s, i) => {
          const on = i === current;
          const done = i < current;
          return (
            <React.Fragment key={s}>
              {i > 0 ? <span style={{ width: 26, height: 3, borderRadius: 3, background: done || on ? c.key : c.border }} /> : null}
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: 12,
                  height: 58,
                  padding: '0 26px 0 12px',
                  borderRadius: 999,
                  background: on ? c.key : 'transparent',
                  color: on ? c.keyInk : done ? c.fg : c.muted,
                  fontSize: 28,
                  fontWeight: on ? 600 : 500,
                  letterSpacing: '-0.01em',
                }}
              >
                <span
                  style={{
                    width: 36,
                    height: 36,
                    borderRadius: '50%',
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: 19,
                    fontWeight: 600,
                    background: on ? 'rgba(0,0,0,0.12)' : done ? c.key : c.chip,
                    color: on || done ? c.keyInk : c.muted,
                  }}
                >
                  {done ? '✓' : i + 1}
                </span>
                {s}
              </span>
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
};
