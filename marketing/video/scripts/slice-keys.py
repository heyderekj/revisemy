"""Cut real keystrokes out of recordings/keyboard.wav into public/sfx.

Overwrites the synthesized key-1..12, space-1..2 and enter with the cleanest
isolated keystrokes from the recording. Run after make-sfx.py (`npm run sfx`
does both). If there is no recording, the synthesized keys stay.
"""

import wave
from pathlib import Path

import numpy as np
from scipy import signal

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "recordings" / "keyboard.wav"
OUT = ROOT / "public" / "sfx"

if not SRC.exists():
    print("no recordings/keyboard.wav — keeping synthesized keys")
    raise SystemExit(0)

with wave.open(str(SRC)) as w:
    sr = w.getframerate()
    ch = w.getnchannels()
    x = np.frombuffer(w.readframes(w.getnframes()), dtype=np.int16).reshape(-1, ch).mean(1) / 32768

# rumble and desk thumps out; the keys live above ~120 Hz
x = signal.sosfiltfilt(signal.butter(2, 120, "highpass", fs=sr, output="sos"), x)

# onsets from a short envelope of the high band, where each keystroke starts sharply
hp = signal.sosfilt(signal.butter(2, 1500, "highpass", fs=sr, output="sos"), x)
env = np.convolve(np.abs(hp), np.ones(int(0.002 * sr)) / int(0.002 * sr), "same")
floor = np.median(env)
raw, _ = signal.find_peaks(env, height=floor * 6, distance=int(0.04 * sr))
# a quieter onset soon after a press is that key's release click, not a new key
peaks = []
for p in raw:
    if peaks and p - peaks[-1] < 0.12 * sr and env[p] < env[peaks[-1]] * 0.6:
        continue
    peaks.append(p)
peaks = np.array(peaks)

pre = int(0.004 * sr)
keys = []
for i, p in enumerate(peaks):
    # walk back to where the sound actually rises out of the floor
    s = p
    while s > 0 and env[s] > floor * 2 and p - s < pre * 3:
        s -= 1
    s = max(0, s - int(0.001 * sr))
    nxt = peaks[i + 1] if i + 1 < len(peaks) else len(x)
    prv = peaks[i - 1] if i > 0 else -10**9
    gap_after = (nxt - p) / sr
    gap_before = (p - prv) / sr
    # keep the release clack when there's room for it, but never the next key
    end = min(p + int(0.13 * sr), nxt - int(0.006 * sr))
    seg = x[s:end].copy()
    # cut before any later hit that's loud enough to be another key
    se = np.convolve(np.abs(seg), np.ones(int(0.002 * sr)) / int(0.002 * sr), "same")
    head = int(0.03 * sr)
    if len(se) > head:
        late = np.where(se[head:] > se[:head].max() * 0.35)[0]
        if len(late):
            seg = seg[: head + late[0] - int(0.004 * sr)]
    if len(seg) < int(0.045 * sr):
        continue
    peak = np.max(np.abs(seg))
    low = signal.sosfilt(signal.butter(2, 600, "lowpass", fs=sr, output="sos"), seg)
    low_ratio = np.sqrt(np.mean(low**2)) / (np.sqrt(np.mean(seg**2)) + 1e-12)
    # how long it rings: time until the envelope falls to 10% of its peak
    e = np.abs(signal.hilbert(seg))
    e = np.convolve(e, np.ones(int(0.004 * sr)) / int(0.004 * sr), "same")
    after = np.where(e[np.argmax(e):] < e.max() * 0.1)[0]
    ring = (after[0] if len(after) else len(e)) / sr
    keys.append(dict(id=len(keys), seg=seg, peak=peak, low=low_ratio, ring=ring, gap=min(gap_after, gap_before), t=p / sr))

print(f"{len(raw)} raw onsets, {len(peaks)} keystrokes, {len(keys)} usable slices")

# isolated ones only (no neighbour within 70 ms), and not the loudest/quietest extremes
iso = [k for k in keys if k["gap"] > 0.07]
peaks_db = np.array([20 * np.log10(k["peak"]) for k in iso])
lo, hi = np.percentile(peaks_db, [20, 92])
mid = [k for k, d in zip(iso, peaks_db) if lo <= d <= hi]

# the space bar is the deepest, longest-ringing key
spaces = sorted(iso, key=lambda k: k["low"] * k["ring"], reverse=True)[:2]
space_ids = {k["id"] for k in spaces}
regular = [k for k in mid if k["id"] not in space_ids]
# most typical first: closest to the median brightness
med = np.median([k["low"] for k in regular])
regular.sort(key=lambda k: abs(k["low"] - med))
regular = regular[:12]
used = space_ids | {k["id"] for k in regular}
enter = max((k for k in iso if k["id"] not in used), key=lambda k: k["peak"], default=spaces[0])


def save(name, seg, peak_db):
    seg = seg.copy()
    fi = int(0.0008 * sr)
    seg[:fi] *= np.linspace(0, 1, fi)
    fo = min(len(seg) // 3, int(0.025 * sr))
    seg[-fo:] *= np.linspace(1, 0, fo) ** 2
    seg = seg / np.max(np.abs(seg)) * 10 ** (peak_db / 20)
    if sr != 48000:
        seg = signal.resample_poly(seg, 48000, sr)
    pcm = (np.clip(seg, -1, 1) * 32767).astype(np.int16)
    with wave.open(str(OUT / f"{name}.wav"), "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(48000)
        w.writeframes(pcm.tobytes())


for i, k in enumerate(regular, start=1):
    save(f"key-{i}", k["seg"], -4)
for i, k in enumerate(spaces, start=1):
    save(f"space-{i}", k["seg"], -3.5)
save("enter", enter["seg"], -3)

print(f"keys from t={[round(k['t'], 2) for k in regular]}")
print(f"spaces from t={[round(k['t'], 2) for k in spaces]}, enter t={round(enter['t'], 2)}")
