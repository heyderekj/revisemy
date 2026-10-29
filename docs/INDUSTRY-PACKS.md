# Industry packs

**Status: the spine and the first overlay ship.** The general homepage skills and `faith-homepage` live in `plugin/skills/`. The other verticals below are still proposals.

## The idea

An industry pack is a skill that brings the rubric for one kind of site. It runs on top of the general homepage skills: the agent already knows what usually goes wrong on any app or tool homepage, and the pack adds what goes wrong in that industry. The marks are sharper and the first review lands closer to done.

Packs work in any agent on their own. With the ReviseMy MCP connected, `review-homepage` turns the critique into a review the person marks up, then hands off to the `design-checkup` loop.

## Where the rules came from

In October 2026 Derek asked faith-app makers on X for their landing pages and answered about twenty, in replies and in ReviseMy reviews. The same notes kept coming back, and they became the rules in `homepage-craft`:

| Pattern | Pages, of about 20 |
|---|---|
| Too much text, not enough visuals | 7 |
| The product isn't shown high up, or a video or background photo stands in for it | 7 |
| A section further down is stronger than the hero | 6 |
| A first-time visitor can't tell what it is or what to do (clever headings, jargon, eyebrows) | 6 |
| No brand identity, or visuals that don't hang together | 7 |
| Logo lockup proportions, thin or overused type | 5 |
| Every section looks the same; screenshots placed at random | 3 |
| Features as a wall of text instead of a grid of the app's own icons | 7 |
| Too many sections, in the wrong order; side columns fighting the main one | 6 |
| A long path to first value (names, onboarding, two store buttons) | 5 |
| Motion that hides content (marquees, scroll-jacking) | 3 |
| Positioned as a social app, or named after an integration | 2 |
| AI imagery with wrong text costing trust | 1 |

Two caveats came from the capture itself: fonts and scroll-triggered sections didn't always render. `review-homepage` reads each shot's `loaded` and `incomplete_images` meta and leaves those parts out rather than marking them missing.

## What ships

| Skill | Role |
|---|---|
| `homepage-craft` | The spine: 15 rules, a review checklist and `REFERENCES.md`, a shelf of reference sites by problem |
| `review-homepage` | The full critique: what's working, strongest section, up to seven changes, references; uses ReviseMy when connected |
| `find-strongest-section` | Promote the strongest section, or rebuild the rest in its image |
| `show-dont-tell` | A visual form for each text-heavy section |
| `cut-homepage` | The cut list, where each cut goes, and the order |
| `brand-pass` | Carry the working brand element through; lockup, type, palette, imagery |
| `faith-homepage` | The first overlay: church, ministry and Bible software |

The skills speak in ReviseMy's voice and never name the sites they came from.

## Pack shape

Each overlay lives at `plugin/skills/<vertical>-homepage/SKILL.md`. It adds rules on top of `homepage-craft`; it never repeats them. A pack:

- opens by saying it loads alongside `homepage-craft` or `review-homepage`
- has hard rules, then the rules for its industry, then a checklist table
- names only real MCP tools, which `DiscoveryTest` checks for every skill in `plugin/skills/`

## The other verticals (proposed)

| Pack | Rubric focus |
|---|---|
| `nonprofit` | Donation and fundraising sites. Impact numbers, a short donate path |
| `health` | Clinics, telehealth, health apps. Trust cues, plain language, booking CTA |
| `fintech` | Payments, banking, personal finance. Security proof, clear pricing |
| `legal` | Law firms and legal software. Credentials, scannable practice areas |
| `realestate` | Agents, listings, proptech. Photo quality, search and contact CTA |
| `education` | Course creators, edtech, schools. Outcomes, curriculum visuals |
| `restaurant` | Restaurants, cafes, hospitality. Menu, hours and booking above the fold |
| `ecommerce` | DTC storefronts and product pages. Product imagery, reviews, checkout path |
| `devtools` | Developer products. Demo or code visual, install in ten seconds |

Each one should come from real feedback the way faith did, not from a guess. Email and slides variants can follow once websites work.

## Distribution

- **Claude Code:** the `revisemy` plugin carries every skill in `plugin/skills/`, so one install gets them all.
- **Other agents:** `npx skills add heyderekj/revisemy`. `skills.sh.json` adds `plugin/skills/` as a discovery root, and the CLI lists only the plugin skills (checked with `npx skills add . --list`). `revisemy-voice` is also marked `internal` so it stays hidden.
- **Pages:** `/skills` lists every pack by category, from `config/skills.php`. Each pack with a `path` gets its own page, such as `/skills/homepage` and `/skills/faith`, drawn by the guide template. A new industry pack is a skill folder plus one `packs` entry with `category: industries`.

## Limits today

- A rubric is only as good as the shot it reads. On the `/screenshot` capture fallback, desktop and tablet are still 1×.
- No per-request rubric on the server. `type` is a closed enum (`ui`, `website`, `presentation`, `email`) and lens prompts are fixed in `app/Support/TasteLenses.php` and `app/Services/SecondOpinionService.php`.
- Findings have no source field, so the `[Rule]` prefix in the body is the only tag.

## Later

Once a rubric proves itself, move it server side:

- An optional `lens` or `rubric` parameter on `create_review`, threaded through `CreateReviewTool`, `ReviewService` and `SecondOpinionService`, with a column on `Review`.
- A source tag on findings, so the board can show which skill raised a mark.

## Open questions

- Which vertical is next, and whose feedback does it come from?
- Marketing sites only, or app UI packs too?
