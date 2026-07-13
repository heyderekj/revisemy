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
        $haystack = strtolower($haystack);
        $motion = self::hasMotionContext($haystack);

        return match ($type) {
            Review::TYPE_WEBSITE => array_values(array_filter([
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
                $motion ? [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Prefer ease-out under ~300ms for UI chrome; never ease-in; don’t animate keyboard-triggered actions.',
                    'area' => null,
                ] : null,
            ])),
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
            default => array_values(array_filter([
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Primary pressables should feel responsive — confirm a clear pressed/active treatment (subtle scale ~0.97), not only a hover color swap.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Floating surfaces often read better with soft depth (semi-transparent shadow/ring) than a hard opaque border.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Type and spacing should follow a clear proportion system — uneven gaps and accidental misalignment read as unfinished.',
                    'area' => null,
                ],
                [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Feedback should start on press (pointer-down), not only on release — dead controls feel like a laggy computer.',
                    'area' => null,
                ],
                $motion ? [
                    'severity' => Finding::SEVERITY_POLISH,
                    'body' => 'Prefer ease-out under ~300ms for UI chrome; never ease-in; don’t animate keyboard-triggered actions; popovers should scale from the trigger (modals stay centered).',
                    'area' => null,
                ] : null,
            ])),
        };
    }

    /**
     * Vision taste-lens markdown for a review type (framework language, not personal quotes).
     */
    public static function visionLensMarkdown(?string $type): string
    {
        $type = self::normalizeType($type);

        return match ($type) {
            Review::TYPE_WEBSITE => <<<'LENS'
Craft lenses (marketing/content website — ReviseMy-distilled public principles; suggestions only):
IIDS (visual craft):
- Typography hierarchy, grids/alignment, proportion, precision, restraint.
- Above the fold: value proposition and next step clear without scrolling.
- One dominant CTA; plain-language nav; readable measure (~45–75 chars).
Design engineering (when chrome/motion is visible):
- Soft depth over hard boxes on floating UI; restrained motion if implied.
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
Design engineering:
- Hierarchy & restraint; pressables need pressed/active affordance (not hover-only).
- Soft shadow/ring often beats hard opaque borders on floating surfaces.
- Motion if implied: ease-out, under ~300ms; never ease-in; no keyboard-triggered motion; popovers from trigger; never scale(0).
IIDS:
- Typography hierarchy, grids/alignment, proportion, precision, restraint, affordance.
Fluid interfaces:
- Respond on press; continuous feedback during interaction; interruptible motion when gesture UI is implied.
Accessibility: contrast, focus visibility, reduced-motion awareness when motion is implied.
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

    protected static function hasMotionContext(string $haystack): bool
    {
        return str_contains($haystack, 'animat')
            || str_contains($haystack, 'modal')
            || str_contains($haystack, 'drawer')
            || str_contains($haystack, 'toast')
            || str_contains($haystack, 'popover')
            || str_contains($haystack, 'dropdown')
            || str_contains($haystack, 'motion');
    }
}
