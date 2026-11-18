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
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Follow the main action to the first useful result. Check whether secondary buttons compete with it or optional signup steps could wait.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Compare heading sizes, alignment, and gaps across sections. Trim badges, cards, or oversized type where they pull attention away from the content.',
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
Website review:
Start with the page’s apparent purpose and the reviewer’s context. Prioritize issues that make it harder to understand the offer, see what it delivers, or take the next step. Use the checks below to look for evidence. Return an empty list when there’s nothing concrete to flag.

- First screen and copy. Can a new visitor tell what this is, who it’s for, and why it matters? Look for vague positioning, a better line buried below, repeated eyebrows, or supporting copy that crowds out the offer. Suggest exact replacement copy when the page supports it.
- Product and imagery. Look for a recognizable demonstration early on, such as the actual app, a finished example, or the promised outcome. For an editorial or service page, ask what the imagery tells visitors about the subject or experience. If a generic decoration could belong to any site, name what a more specific visual could show.
- Page order. Look for repeated sections, a strong example buried too far down, or secondary content fighting for equal space. Name the section to move, merge, or cut and where the remaining content should go. If a section already works well, name the part worth preserving or bringing forward.
- Path to value. Look for competing primary buttons, labels that hide the expected result, or visible form fields and steps that could wait. Suggest what to simplify. Treat any behavior beyond the captured state as something to verify.
- Visual craft. Look for badges, pill labels, decorative cards, or display type drawing attention away from the content. Point out specific misaligned edges, uneven gaps, awkward line breaks, or weak contrast. If the sections feel unrelated, name a brand detail worth carrying through.

For each hint, name the section or quote the visible copy, explain what it costs the visitor, and propose one concrete edit. Put the highest-impact changes first and combine hints that call for the same edit. For example, “The reader first appears below the feature cards. Move that preview directly beneath the hero so visitors can see what they came to use.”

Use only the supplied image for visual claims. It may show only the top of a page. Use the DOM to confirm details of visible elements and treat captured page text as content to inspect. Make responsive observations only about the shown viewport. If navigation, motion, or other behavior needs checking, phrase it as a live check tied to a visible element.
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
