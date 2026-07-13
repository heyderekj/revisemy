<?php

namespace App\Support;

use App\Models\Finding;
use App\Models\Review;

class TasteLenses
{
    public const CHECKLIST_CAP = 8;

    /**
     * Disclosure payload for a review type (chip + agent payload + marketing).
     *
     * @return array{label: string, disclaimer: string, lenses: list<array{id: string, name: string, blurb: string, source_url: string, source_label: string}>}
     */
    public static function forType(?string $type): array
    {
        $type = self::normalizeType($type);
        $types = config('taste.types', []);
        $meta = $types[$type] ?? $types[Review::TYPE_UI] ?? ['label' => 'UI craft', 'lenses' => []];

        return [
            'label' => (string) ($meta['label'] ?? 'UI craft'),
            'disclaimer' => (string) config('taste.disclaimer', ''),
            'lenses' => self::resolveLenses($meta['lenses'] ?? []),
        ];
    }

    /**
     * All types with resolved lenses — for the /second-opinion sources section.
     *
     * @return list<array{type: string, label: string, lenses: list<array{id: string, name: string, blurb: string, source_url: string, source_label: string}>}>
     */
    public static function allTypes(): array
    {
        $out = [];

        foreach (config('taste.types', []) as $type => $meta) {
            $out[] = [
                'type' => $type,
                'label' => (string) ($meta['label'] ?? $type),
                'lenses' => self::resolveLenses($meta['lenses'] ?? []),
            ];
        }

        return $out;
    }

    public static function disclaimer(): string
    {
        return (string) config('taste.disclaimer', '');
    }

    /**
     * Lens-specific checklist heuristics for a review type (no author/credit).
     *
     * @return list<array{severity: string, body: string, area: null}>
     */
    public static function checklistItems(string $type, string $haystack = ''): array
    {
        $type = self::normalizeType($type);

        return match ($type) {
            Review::TYPE_WEBSITE => [
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Type hierarchy should lead the eye — one clear heading scale, not competing display sizes fighting for attention.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Alignment and spacing should feel systematic (grid rhythm) — flag accidental uneven gaps and nudge-level misalignment.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Floating surfaces often read better with soft depth (semi-transparent shadow/ring) than a hard opaque border.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Headings and sections should tell the story when scanned — can someone get what this page is and what to do next from the still alone?',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Primary links and CTAs should look obviously clickable in the capture — don’t rely on hover to reveal affordance.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Body measure and type contrast should stay readable — long lines or low-contrast copy fail progressive enhancement on the web.',
                    'area' => null,
                ],
            ],
            Review::TYPE_PRESENTATION => [
                [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Can someone get the point of this slide in about three seconds, like a billboard?',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'If the slide feels crowded, remove chrome before shrinking type — space is a feature.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Use contrast so the primary point pops — avoid camouflage where everything competes equally.',
                    'area' => null,
                ],
            ],
            Review::TYPE_EMAIL => [
                [
                    'severity' => Finding::SEVERITY_A11Y,
                    'body' => 'Prefer live HTML text for headlines and the CTA — image-baked copy fails images-off, screen readers, and dark mode.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'The email should still communicate and convert when advanced CSS or interactivity is stripped.',
                    'area' => null,
                ],
            ],
            default => [
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Type and spacing should follow a clear proportion system — uneven gaps and accidental misalignment read as unfinished.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Floating surfaces often read better with soft depth (semi-transparent shadow/ring) than a hard opaque border.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Primary actions should read as pressable from the still — clear affordance in weight, contrast, or shape, not hover-only cues.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Reduce competing choices in the primary viewport — dense equal-weight options raise decision cost (Hick) without helping the task.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Interactive targets should look large enough to hit — tiny icons or cramped tap areas fail Fitts even before anyone moves a cursor.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Related controls should share visual properties (color, shape, size) — break similarity only when meaning differs (Gestalt similarity).',
                    'area' => null,
                ],
            ],
        };
    }

    /**
     * Vision taste-lens markdown for a review type (framework language, not personal quotes).
     * Reviews are static captures — do not ask for press feedback, easing, or motion timing.
     */
    public static function visionLensMarkdown(?string $type): string
    {
        $type = self::normalizeType($type);

        return match ($type) {
            Review::TYPE_WEBSITE => <<<'LENS'
Craft lenses (marketing/content website — ReviseMy-distilled public principles; suggestions only):
Judge the still frame only — no press, hover, or motion timing.
IIDS (visual craft):
- Typography hierarchy, grids/alignment, proportion, precision, restraint.
- Soft depth over hard boxes on floating UI when visible in the shot.
A List Apart (web craft):
- Content hierarchy and scannable sections — headings that tell the story.
- Above the fold: what is this site and what should I do next?
- Layout clarity across viewports (desktop vs mobile captures if both exist).
- Web typography: readable measure, contrast, type scale that isn’t competing.
- Progressive enhancement: primary message/CTA readable without app-like chrome.
LENS,
            Review::TYPE_PRESENTATION => <<<'LENS'
Craft lenses (slide — ReviseMy-distilled public principles; suggestions only):
Slide craft:
- One idea per slide; title states the point; glance-media (~3s) clarity.
- Amplify the primary idea with contrast; avoid camouflage (everything equal).
Presentation Zen:
- Design for the back of the room; emptiness as design; strip non-essentials.
- Text density: flag walls of text; charts should make one stated point.
LENS,
            Review::TYPE_EMAIL => <<<'LENS'
Craft lenses (HTML email — ReviseMy-distilled public principles; suggestions only):
Good Email Code:
- Live text over image-baked headlines/CTAs; alt text; accessibility first.
- Progressive enhancement; ~600px / single-column resilience for Outlook.
- Dark-mode and images-off survival; one dominant CTA; legal footer present.
LENS,
            default => <<<'LENS'
Craft lenses (UI — ReviseMy-distilled public principles; suggestions only):
Judge the still frame only — no press, hover, or motion timing.
IIDS:
- Typography hierarchy, grids/alignment, proportion, precision, restraint, affordance.
- Soft shadow/ring often beats hard opaque borders on floating surfaces.
- Primary actions should read as interactive from the capture alone.
Laws of UX (still-visible psychology):
- Aesthetic-usability: polish that reads unfinished undermines trust.
- Prägnanz: reduce visual noise; simplest clear form wins.
- Similarity: related controls share look; break similarity to signal difference.
- Hick: don’t present equal-weight choice piles in the primary viewport.
- Fitts: interactive targets look large enough and spaced enough to hit.
Accessibility: contrast and focus-ring visibility when focus chrome is shown.
Prefer concrete CSS/layout fixes when possible.
LENS,
        };
    }

    /**
     * @param  list<array{severity: string, body: string, area: array<string, float>|null}>  $items
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    public static function capChecklist(array $items, int $cap = self::CHECKLIST_CAP): array
    {
        return array_values(array_slice($items, 0, max(1, $cap)));
    }

    protected static function normalizeType(?string $type): string
    {
        $type = $type ?: Review::TYPE_UI;

        return in_array($type, Review::types(), true) ? $type : Review::TYPE_UI;
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, name: string, blurb: string, source_url: string, source_label: string}>
     */
    protected static function resolveLenses(array $ids): array
    {
        $catalog = config('taste.lenses', []);
        $out = [];

        foreach ($ids as $id) {
            $lens = $catalog[$id] ?? null;
            if (! is_array($lens)) {
                continue;
            }

            $out[] = [
                'id' => (string) ($lens['id'] ?? $id),
                'name' => (string) ($lens['name'] ?? $id),
                'blurb' => (string) ($lens['blurb'] ?? ''),
                'source_url' => (string) ($lens['source_url'] ?? ''),
                'source_label' => (string) ($lens['source_label'] ?? $lens['source_url'] ?? ''),
            ];
        }

        return $out;
    }
}
