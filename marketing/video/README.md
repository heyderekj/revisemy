# ReviseMy tour video

The ~68s homepage tour, built with [Remotion](https://www.remotion.dev). It renders a light and a dark cut from the same timeline; the site plays whichever matches the page (`resources/views/components/pitch-video.blade.php`).

```sh
cd marketing/video
npm install
npm run studio          # scrub it in Remotion Studio
npm run render          # both cuts → out/
npm run render:light    # or just one
npm run render:dark
```

Then encode for the web and replace `public/videos/revisemy-pitch-{light,dark}.mp4` at the site root:

```sh
ffmpeg -i out/revisemy-pitch.mp4 -c:v libx264 -preset slow -crf 25 -pix_fmt yuv420p -movflags +faststart -c:a aac -b:a 128k ../../public/videos/revisemy-pitch-light.mp4
```

(Same for the dark cut.) The poster images in `public/images/pitch/` are stills of the review scene: `npx remotion still scene-review out/poster.png --frame=372 --props='{"mode":"dark"}'`.

## Where things are

- `src/copy.ts` — every word on screen.
- `src/theme.ts` — light and dark tokens, copied from `resources/css/tokens.css`. Components read `c.x` (CSS variables); `<Themed>` in `src/Video.tsx` sets them.
- `src/scenes/` — one file per scene; frame timings sit at the top of each.
- `scripts/make-sfx.py` — synthesizes the UI sounds. `scripts/slice-keys.py` then cuts real keystrokes out of `recordings/keyboard.wav` (replace that file with your own typing and run `npm run sfx`).
- `scripts/render.sh` — renders, then sets the mix to about -18 LUFS with a limiter.

Remotion is free for individuals and companies of three or fewer; larger teams need a company license.
