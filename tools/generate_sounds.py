#!/usr/bin/env python3
"""
generate_sounds.py
-------------------
Synthesizes two short sound effects for the photobooth, with no external
binary assets and no dependencies beyond the Python standard library:

  - beep.wav     : short rising sine "tick" played on each countdown second
  - shutter.wav  : a quick noise-burst "click" played at the moment of capture

Run with: python3 tools/generate_sounds.py
"""
import math
import struct
import wave
import os
import random

OUT_DIR = os.path.join(os.path.dirname(__file__), "..", "photobooth", "assets", "sounds")
os.makedirs(OUT_DIR, exist_ok=True)

SAMPLE_RATE = 44100


def write_wav(path, samples, sample_rate=SAMPLE_RATE):
    """samples: list of floats in range [-1, 1]"""
    with wave.open(path, "w") as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(sample_rate)
        frames = b"".join(struct.pack("<h", int(max(-1.0, min(1.0, s)) * 32767)) for s in samples)
        wf.writeframes(frames)


def envelope(i, n, attack=0.05, release=0.4):
    """Simple linear attack/release envelope, value 0..1"""
    a = int(n * attack)
    r = int(n * release)
    if i < a:
        return i / max(1, a)
    if i > n - r:
        return max(0.0, (n - i) / max(1, r))
    return 1.0


def make_beep(freq=880.0, duration=0.12, volume=0.5):
    n = int(SAMPLE_RATE * duration)
    out = []
    for i in range(n):
        t = i / SAMPLE_RATE
        s = math.sin(2 * math.pi * freq * t)
        s *= envelope(i, n, attack=0.1, release=0.6)
        out.append(s * volume)
    return out


def make_shutter(duration=0.18, volume=0.8):
    """Quick broadband click: filtered noise burst + a short percussive thump."""
    n = int(SAMPLE_RATE * duration)
    out = []
    prev = 0.0
    for i in range(n):
        t = i / SAMPLE_RATE
        noise = random.uniform(-1, 1)
        # cheap 1-pole low-pass to soften harsh white noise into a "click" timbre
        prev = prev * 0.55 + noise * 0.45
        thump = math.sin(2 * math.pi * 180 * t) * math.exp(-25 * t)
        s = (prev * 0.6 + thump * 0.7)
        s *= envelope(i, n, attack=0.01, release=0.85)
        out.append(s * volume)
    return out


if __name__ == "__main__":
    write_wav(os.path.join(OUT_DIR, "beep.wav"), make_beep())
    write_wav(os.path.join(OUT_DIR, "shutter.wav"), make_shutter())
    print("Wrote beep.wav and shutter.wav to", OUT_DIR)
