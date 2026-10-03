#!/bin/sh
# Render the pitch, then bring the mix to about -18 LUFS (web/social) with a
# gentle limiter so transients never clip.
set -e
cd "$(dirname "$0")/.."
# Usage: scripts/render.sh [light|dark]
MODE=${1:-light}
COMP=ReviseMyPitch; NAME=revisemy-pitch
if [ "$MODE" = dark ]; then COMP=ReviseMyPitch-dark; NAME=revisemy-pitch-dark; fi
npx remotion render src/index.ts "$COMP" out/raw.mp4 --codec h264 --crf 18 --log=error
I=$(ffmpeg -nostats -i out/raw.mp4 -vn -af ebur128 -f null - 2>&1 | awk '/^ *I:/ {v=$2} END {print v}')
GAIN=$(echo "-18 - ($I)" | bc -l)
echo "measured ${I} LUFS, applying ${GAIN} dB"
ffmpeg -y -loglevel error -i out/raw.mp4 -c:v copy \
  -af "volume=${GAIN}dB,alimiter=limit=0.7:attack=3:release=60:level=disabled,aresample=48000" \
  -c:a aac -b:a 192k "out/$NAME.mp4"
rm out/raw.mp4
echo "out/$NAME.mp4"
