#!/bin/sh
# Usage: scripts/stills.sh name f1 f2 ... — bundles once, renders stills, tiles them 3-up into out/<name>.png
set -e
cd "$(dirname "$0")/.."
name=$1; shift
comp=${COMP:-ReviseMyPitch}
mode=${MODE:-light}
npx remotion bundle src/index.ts --out-dir out/bundle --log=error >/dev/null
inputs=""; n=0
for f in "$@"; do
  npx remotion still out/bundle "$comp" "out/st-$f.png" --frame="$f" --props="{\"mode\":\"$mode\"}" --log=error --scale=0.5 >/dev/null &
done
wait
for f in "$@"; do inputs="$inputs -i out/st-$f.png"; n=$((n+1)); done
cols=3; layout=""; i=0
for f in "$@"; do
  x=$((i % cols)); y=$((i / cols))
  [ -n "$layout" ] && layout="$layout|"
  layout="${layout}$((x*960))_$((y*540))"
  i=$((i+1))
done
if [ "$n" -gt 1 ]; then
  ffmpeg -y -loglevel error $inputs -filter_complex "xstack=inputs=$n:layout=$layout:fill=black" "out/$name.png"
else
  cp "out/st-$1.png" "out/$name.png"
fi
echo "out/$name.png"
