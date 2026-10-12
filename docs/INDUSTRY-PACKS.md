# Industry packs

**Status: future. Not built.** This captures the idea so we can pick it up later. Nothing here exists in code, config or the plugin yet.

## The idea

An industry pack is a skill that runs on the ReviseMy MCP and brings a rubric for one kind of site. The agent does the same loop as `design-checkup`, but it already knows what usually goes wrong in that industry, so the marks are sharper and the first review lands closer to done.

Why start with marketing sites: many niche products ship pages that say a lot and show very little. A pack names that and marks it.

## Worked example: faith tools

Take the Sermonary homepage (sermonary.com). It reads well, but most of it is text.

- Only the six-step workflow (Plan, Study, Write, Present, Recap, Multiply) is visual. Each step has a screenshot.
- The problem section, the feature checklist, the FAQ and the pricing and Team details are text only.
- Testimonials are named quotes with no logos, counts or ratings.
- It's unclear whether the hero carries a real product screenshot.

Most faith-tool sites follow the same shape. A faith pack would mark each of those sections and ask for something to look at: a real product shot in the hero, an illustration or before and after for the problem, a face and church name beside each quote, a comparison table for plans.

## Decisions so far

- **Form:** a skill pack with a rubric. No server change.
- **First audience:** faith-tool founders and marketers, who audit their own site.
- **Scope:** ten packs in total, faith first.

## The ten packs (proposed)

| Pack | Rubric focus |
|---|---|
| `faith` | Church and ministry software. Show the product, proof with faces and churches, reverent not stock-church imagery |
| `nonprofit` | Donation and fundraising sites. Impact numbers, a short donate path |
| `health` | Clinics, telehealth, health apps. Trust cues, plain language, booking CTA |
| `fintech` | Payments, banking, personal finance. Security proof, clear pricing |
| `legal` | Law firms and legal software. Credentials, scannable practice areas |
| `realestate` | Agents, listings, proptech. Photo quality, search and contact CTA |
| `education` | Course creators, edtech, schools. Outcomes, curriculum visuals |
| `restaurant` | Restaurants, cafes, hospitality. Menu, hours and booking above the fold |
| `ecommerce` | DTC storefronts and product pages. Product imagery, reviews, checkout path |
| `devtools` | Developer products. Demo or code visual, install in ten seconds |

The nine after `faith` are proposed, not confirmed. Email and slides variants (`<vertical>-email`) can follow once websites work.

## Pack shape

Each pack lives at `plugin/skills/<vertical>-website-checkup/`:

- `SKILL.md`: the trigger and the loop.
- `rubric.md`: the industry rubric.

The loop is the same across packs:

1. `create_review` with `type: website`, `capture_url: true` and a `context` that names the industry and page type.
2. Run the rubric against the capture.
3. Push up to 20 findings per call with `add_findings`. Prefix each body with its rubric area, since findings carry no source label.
4. Share the `review_url`, then poll `get_review` and follow `next_action`.
5. Call `resolve_marks` as each mark is fixed.

Packs point at the MCP server. They never reimplement tools (see "Agent skill" in `docs/CONNECTORS.md`).

The rubric spine is shared: show, don't tell; text to visual ratio per section; proof; the five-second value test; one primary CTA; mobile and contrast; pricing clarity. Each pack adds its own trust cues and page types (home, pricing, feature).

## How it plugs into the repo

- **Distribution:** one plugin, so one install gets every pack. Skills sit beside `design-checkup` in `plugin/skills/` and ship through `.claude-plugin/marketplace.json`.
- **Cursor:** the `.cursor/skills/` copies are hand-maintained today. Ten more would be a chore, so generate them from the plugin copies with a sync command.
- **Pages:** one `/for/<vertical>` page per pack, as `audiences` entries in `config/use-cases.php`. Slugs must be one word, because the route pattern is `[a-z]+`. The sitemap, llms.txt and the `.md` twins pick new entries up on their own.
- **Tests:** `DiscoveryTest` checks only `design-checkup` today. Extend it to every skill under `plugin/skills/`, and assert each pack has a `rubric.md`.
- **Release:** `php artisan revisemy:bump` and a changelog entry.
- **Copy:** follow `docs/positioning.md` and the `revisemy-voice` skill.

## Limits today

- No per-request rubric. `type` is a closed enum (`ui`, `website`, `presentation`, `email`) and lens prompts are fixed in `app/Support/TasteLenses.php` and `app/Services/SecondOpinionService.php`.
- `context` reaches the vision prompt, but only as a soft hint.
- Findings have no source field, so the prefix in the body is the only tag.

## Later

Once a rubric proves itself, move it server side:

- An optional `lens` or `rubric` parameter on `create_review`, threaded through `CreateReviewTool`, `ReviewService` and `SecondOpinionService`, with a column on `Review`.
- A source tag on findings, so the board can show which pack raised a mark.

## Sequencing

1. Shared spine, the faith pack, `/for/faith`, the sync command and the test changes.
2. The other nine packs and pages.

## Open questions

- Which nine verticals, and in what order?
- Marketing sites only, or app UI packs too?
- One marketplace entry for all packs, or one each?
