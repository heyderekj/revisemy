// Builds src/vo/manifest.json from src/vo/script.json and public/vo/.
// For each line: its length in frames, and when each word starts (frames from
// the line's start). Real audio wins: duration from ffprobe, word starts from
// public/vo/<id>.json (ElevenLabs character alignment) when present. Without
// audio, both are estimated so the timeline can be built before recording.
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

const FPS = 30;
const WPS = 2.55; // estimated speaking rate, words per second
const root = new URL('..', import.meta.url).pathname;
const script = JSON.parse(readFileSync(`${root}src/vo/script.json`, 'utf8'));

const lines = {};
for (const [id, text] of Object.entries(script)) {
  const words = text.split(/\s+/);
  const audio = `${root}public/vo/${id}.mp3`;
  const align = `${root}public/vo/${id}.json`;
  let seconds;
  let starts;
  if (existsSync(audio)) {
    seconds = Number(
      execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', audio]).toString().trim(),
    );
  }
  if (existsSync(align)) {
    // { characters: [...], character_start_times_seconds: [...] }
    const a = JSON.parse(readFileSync(align, 'utf8'));
    const chars = a.characters ?? a.alignment?.characters;
    const times = a.character_start_times_seconds ?? a.alignment?.character_start_times_seconds;
    starts = [];
    let inWord = false;
    chars.forEach((ch, i) => {
      if (/\s/.test(ch)) inWord = false;
      else if (!inWord) {
        inWord = true;
        starts.push(Math.round(times[i] * FPS));
      }
    });
  }
  if (seconds === undefined) seconds = words.length / WPS + 0.35;
  if (!starts || starts.length !== words.length) {
    // spread words evenly across the speech, weighted by length
    const total = words.reduce((s, w) => s + w.length + 2, 0);
    let acc = 0;
    starts = words.map((w) => {
      const f = Math.round((acc / total) * (seconds - 0.2) * FPS);
      acc += w.length + 2;
      return f;
    });
  }
  lines[id] = { text, frames: Math.ceil(seconds * FPS), words: starts, real: existsSync(audio) };
}

writeFileSync(`${root}src/vo/manifest.json`, JSON.stringify({ fps: FPS, lines }, null, 2) + '\n');
const total = Object.values(lines).reduce((s, l) => s + l.frames, 0);
console.log(`${Object.keys(lines).length} lines, ${(total / FPS).toFixed(1)}s of speech, ${Object.values(lines).filter((l) => l.real).length} recorded`);
