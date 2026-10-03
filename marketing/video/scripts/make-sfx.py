"""Synthesize the video's sound effects into public/sfx/*.wav.

Everything is built from damped sine "modes" and filtered noise, so there is
nothing to license. Objects that get struck (keys, mouse buttons, a stamp)
ring at a few resonant frequencies that die away fast; modelling that, rather
than filtering raw noise, is what keeps them from sounding like static.

Run with `npm run sfx` (python3 + numpy + scipy).
"""

import wave
from pathlib import Path

import numpy as np
from scipy import signal

SR = 48000
OUT = Path(__file__).resolve().parent.parent / "public" / "sfx"
rng = np.random.default_rng(11)


# --- building blocks ------------------------------------------------------


def n(dur):
    return int(SR * dur)


def tt(dur):
    return np.arange(n(dur)) / SR


def noise(dur):
    return rng.standard_normal(n(dur))


def sos(kind, f, order=2):
    return signal.butter(order, f, btype=kind, fs=SR, output="sos")


def band(x, lo, hi, order=2):
    return signal.sosfilt(sos("bandpass", [lo, hi], order), x)


def low(x, f, order=2):
    return signal.sosfilt(sos("lowpass", f, order), x)


def high(x, f, order=2):
    return signal.sosfilt(sos("highpass", f, order), x)


def env(dur, tau, attack=0.0015):
    e = np.exp(-tt(dur) / tau)
    a = max(1, n(attack))
    e[:a] *= np.sin(np.linspace(0, np.pi / 2, a)) ** 2
    return e


def mode(freq, tau, amp, dur, attack=0.0008):
    """One damped resonance, random phase."""
    ph = rng.uniform(0, 2 * np.pi)
    return amp * np.sin(2 * np.pi * freq * tt(dur) + ph) * env(dur, tau, attack)


def place(x, offset, dur):
    out = np.zeros(n(dur))
    o = n(offset)
    seg = x[: max(0, len(out) - o)]
    out[o : o + len(seg)] += seg
    return out


def mix(*parts):
    m = max(len(p) for p in parts)
    return sum(np.pad(p, (0, m - len(p))) for p in parts)


# A small, soft room shared by every sound so they read as one set.
_ir_t = tt(0.32)
_ir = band(noise(0.32), 300, 6000) * np.exp(-_ir_t / 0.07)
_ir[: n(0.004)] = 0
_ir /= np.sqrt(np.sum(_ir**2))


def room(x, wet=0.12):
    y = signal.fftconvolve(x, _ir)[: len(x) + n(0.25)]
    dry = np.pad(x, (0, len(y) - len(x)))
    return dry + wet * y * (np.max(np.abs(x)) / (np.max(np.abs(y)) + 1e-9))


def finish(x, ms=8):
    x = x.copy()
    k = n(ms / 1000)
    x[-k:] *= np.linspace(1, 0, k)
    # trim trailing near-silence
    idx = np.where(np.abs(x) > 1e-4 * np.max(np.abs(x)))[0]
    return x[: idx[-1] + 1] if len(idx) else x


def save(name, x, peak_db=-3.0, wet=0.12, lead=0.0):
    x = room(x, wet) if wet else x
    x = low(x, 9000)
    x = finish(x)
    if lead:
        x = np.concatenate([np.zeros(n(lead)), x])
    x = x / (np.max(np.abs(x)) or 1) * 10 ** (peak_db / 20)
    pcm = (np.clip(x, -1, 1) * 32767).astype(np.int16)
    with wave.open(str(OUT / f"{name}.wav"), "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes(pcm.tobytes())


for old in OUT.glob("*.wav"):
    old.unlink()

# --- keyboard -------------------------------------------------------------


def keystroke(pitch=1.0, weight=1.0, bright=1.0, dur=0.16):
    """A laptop/low-profile key, built from shaped noise rather than tones.

    Real keystrokes are almost all noise: a click of contact, a short
    broadband clack, a muffled thock as the key bottoms out, and a fainter
    clack on release. Nothing rings long enough to have a pitch, so nothing
    here does either (the one resonance dies in ~1 ms and only adds bite).
    """
    r = lambda a, b: rng.uniform(a, b)  # noqa: E731
    down = mix(
        # finger contact: tiny, bright
        high(noise(0.003), 3000) * env(0.003, r(0.0004, 0.0007), 0.0002) * 0.45 * bright,
        # keycap clack: wide band of noise, very short
        band(noise(dur), r(1400, 1900) * pitch, r(5000, 7000)) * env(dur, r(0.0016, 0.0026), 0.0003) * 0.8 * bright,
        # bottoming out: muffled noise, a little longer
        band(noise(dur), r(160, 220) * pitch, r(800, 1100) * pitch, order=1) * env(dur, r(0.005, 0.008), 0.0012) * 0.9 * weight,
        # a hint of hard plastic, gone almost at once
        mode(r(2600, 3400) * pitch, r(0.0006, 0.0010), 0.25 * bright, dur, attack=0.0002),
    )
    up = mix(
        band(noise(dur), r(1800, 2400), r(6000, 8000)) * env(dur, r(0.0010, 0.0016), 0.0002) * 0.6,
        band(noise(dur), 300, 1200, order=1) * env(dur, 0.003, 0.0008) * 0.3,
    )
    out = mix(down, place(up * r(0.18, 0.3), r(0.05, 0.085), dur))
    return high(out, 90)


def tonality(x):
    """Peak-to-median spectral ratio in dB: high = pitched, low = noisy."""
    spec = np.abs(np.fft.rfft(x * np.hanning(len(x)), 8192))
    f = np.fft.rfftfreq(8192, 1 / SR)
    sel = (f > 100) & (f < 8000)
    return 20 * np.log10(spec[sel].max() / np.median(spec[sel]))


# 12 key variants, each with a little random lead-in so keystrokes don't all
# land exactly on video frame boundaries (33 ms apart) and sound quantized.
for i in range(1, 13):
    k = keystroke(pitch=rng.uniform(0.9, 1.1), weight=rng.uniform(0.8, 1.15), bright=rng.uniform(0.7, 1.0))
    save(f"key-{i}", k, peak_db=-4, wet=0.06, lead=rng.uniform(0, 0.026))
    if i == 1:
        print(f"key tonality {tonality(k):.1f} dB")

for i in range(1, 3):
    sp = mix(
        keystroke(pitch=0.7, weight=1.6, bright=0.5, dur=0.22),
        # stabilizer wire rattle
        place(band(noise(0.01), 1500, 4000) * env(0.01, 0.002) * 0.12, 0.007, 0.22),
    )
    save(f"space-{i}", sp, peak_db=-4, wet=0.08, lead=rng.uniform(0, 0.02))

save("enter", mix(keystroke(0.75, 1.4, 0.7, 0.22), place(keystroke(0.8, 0.6, 0.6, 0.12) * 0.25, 0.018, 0.22)), peak_db=-3.5, wet=0.1)

# --- mouse ----------------------------------------------------------------


def mouse_half(pitch=1.0, amp=1.0, dur=0.06):
    return amp * mix(
        low(noise(0.003), 7000) * env(0.003, 0.0006, 0.0003) * 0.3,
        mode(2900 * pitch, 0.0018, 0.6, dur),
        mode(4300 * pitch, 0.0011, 0.3, dur),
        mode(1150 * pitch, 0.0035, 0.35, dur),
        mode(320 * pitch, 0.006, 0.2, dur, attack=0.0015),
    )


down = mouse_half(1.0)
up = mouse_half(1.12, 0.45)
save("click-down", down, peak_db=-5, wet=0.1)
save("click-up", up, peak_db=-9, wet=0.1)
save("click", mix(down, place(up, 0.07, 0.15)), peak_db=-5, wet=0.1)

# --- air movement ---------------------------------------------------------


def pink(dur):
    """Approximate pink noise: softer and less hissy than white."""
    w = noise(dur)
    b = [0.049922035, -0.095993537, 0.050612699, -0.004408786]
    a = [1, -2.494956002, 2.017265875, -0.522189400]
    return signal.lfilter(b, a, w)


def swoosh(dur, f0, f1, f2, peak=0.45):
    """Pink noise through a low-pass whose cutoff rises then falls, under a smooth swell."""
    x = pink(dur)
    m = len(x)
    pk = int(m * peak)
    cut = np.concatenate([np.geomspace(f0, f1, pk), np.geomspace(f1, f2, m - pk)])
    out = np.zeros(m)
    hop = 240
    win = np.hanning(hop * 2)
    for s in range(0, m - hop * 2, hop):
        seg = low(x[s : s + hop * 2], cut[s + hop], order=2)
        out[s : s + hop * 2] += seg * win
    e = np.concatenate([np.sin(np.linspace(0, np.pi / 2, pk)) ** 2, np.cos(np.linspace(0, np.pi / 2, m - pk)) ** 1.5])
    return high(out * e, 120)


save("whoosh", swoosh(0.75, 250, 2200, 500), peak_db=-9, wet=0.15)
save("swipe", swoosh(0.34, 500, 3200, 1200, 0.4), peak_db=-11, wet=0.1)
save("slide", swoosh(0.28, 300, 1400, 600, 0.5), peak_db=-13, wet=0.08)

# --- UI tones -------------------------------------------------------------


def bubble(f0, f1, dur=0.11, tau=0.03):
    t = tt(dur)
    freq = f1 + (f0 - f1) * np.exp(-t / 0.014)
    tone = np.sin(2 * np.pi * np.cumsum(freq) / SR) * env(dur, tau, attack=0.003)
    puff = low(noise(dur), 1800) * env(dur, 0.006, 0.001) * 0.12
    return mix(tone, puff)


save("pop", bubble(560, 250), peak_db=-7, wet=0.12)
save("pop-high", bubble(820, 400, 0.09, 0.022), peak_db=-9, wet=0.12)

# a soft wooden tick, not a beep
save("tick", mix(mode(1650, 0.006, 1.0, 0.07, 0.001), mode(3300, 0.0025, 0.25, 0.07, 0.001), mode(420, 0.008, 0.25, 0.07, 0.002)), peak_db=-9, wet=0.12)


def marimba(freq, dur=1.2):
    return mix(
        mode(freq, 0.32, 1.0, dur, attack=0.004),
        mode(freq * 4.0, 0.05, 0.22, dur, attack=0.002),
        mode(freq * 10.0, 0.012, 0.06, dur, attack=0.001),
        mode(freq * 1.003, 0.3, 0.3, dur, attack=0.004),
    )


save(
    "chime",
    mix(marimba(784.0), place(marimba(987.8), 0.075, 1.4), place(marimba(1174.7) * 0.85, 0.15, 1.5)),
    peak_db=-8,
    wet=0.25,
)

# --- marker, drag, stamp -------------------------------------------------


def pencil(dur=0.65, strokes=6.0):
    """Granular pencil-on-paper: lots of tiny scratches riding a stroke envelope."""
    m = n(dur)
    out = np.zeros(m)
    t = tt(dur)
    stroke = 0.35 + 0.65 * np.abs(np.sin(np.pi * strokes * t / dur + rng.uniform(0, 1))) ** 1.5
    pos = 0
    while pos < m:
        g = n(rng.uniform(0.002, 0.007))
        grain = band(noise(g / SR + 0.0005), rng.uniform(1800, 2800), rng.uniform(4200, 6500)) * np.hanning(len(noise(g / SR + 0.0005)))
        amp = stroke[min(pos, m - 1)] * rng.uniform(0.3, 1.0)
        end = min(m, pos + len(grain))
        out[pos:end] += grain[: end - pos] * amp
        pos += n(rng.uniform(0.004, 0.012))
    paper = low(pink(dur), 700) * stroke * 0.25
    e = np.sin(np.linspace(0, np.pi, m)) ** 0.4
    return (out + paper) * e


save("scribble", pencil(), peak_db=-15, wet=0.1)

drag = low(pink(0.5), 900) * np.sin(np.linspace(0, np.pi, n(0.5))) ** 0.8
save("drag", drag, peak_db=-17, wet=0.05)

stamp = mix(
    mode(72, 0.05, 1.0, 0.4, attack=0.002),
    mode(150, 0.025, 0.4, 0.4, attack=0.002),
    low(noise(0.4), 1400) * env(0.4, 0.018) * 0.5,
    low(noise(0.4), 6000) * env(0.4, 0.0015, 0.0004) * 0.25,
)
save("stamp", stamp, peak_db=-5, wet=0.15)

print("wrote", len(list(OUT.glob("*.wav"))), "files")
