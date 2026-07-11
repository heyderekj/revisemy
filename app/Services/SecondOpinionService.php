<?php

namespace App\Services;

use App\Jobs\GenerateSecondOpinionJob;
use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use App\Services\Vision\AnthropicVisionProvider;
use App\Services\Vision\OpenAiVisionProvider;
use App\Services\Vision\VisionProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SecondOpinionService
{
    public function queue(Screenshot $screenshot): void
    {
        if (! config('revisemy.second_opinion_enabled', true)) {
            return;
        }

        $screenshot->update([
            'second_opinion_status' => Screenshot::OPINION_QUEUED,
            'second_opinion_error' => null,
        ]);

        GenerateSecondOpinionJob::dispatch($screenshot->id);
    }

    public function generate(Screenshot $screenshot): void
    {
        $screenshot->loadMissing('review');

        $screenshot->update([
            'second_opinion_status' => Screenshot::OPINION_QUEUED,
            'second_opinion_error' => null,
        ]);

        try {
            // Replace system findings; keep agent-submitted ones.
            $screenshot->findings()
                ->whereIn('source', [Finding::SOURCE_CHECKLIST, Finding::SOURCE_OPENAI, Finding::SOURCE_ANTHROPIC])
                ->delete();

            $checklist = $this->checklistFindings($screenshot);
            $this->persistFindings($screenshot, $checklist, Finding::SOURCE_CHECKLIST);

            $provider = $this->visionProvider();
            if ($provider) {
                $vision = $provider->findings($screenshot, $this->visionPrompt($screenshot->review));
                $merged = $this->dedupeAgainstExisting($screenshot->fresh('findings'), $vision);
                $this->persistFindings($screenshot, $merged, $provider->source());
            }

            $screenshot->update([
                'second_opinion_status' => Screenshot::OPINION_READY,
                'second_opinion_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Second opinion failed', [
                'screenshot_id' => $screenshot->id,
                'message' => $e->getMessage(),
            ]);

            $screenshot->update([
                'second_opinion_status' => Screenshot::OPINION_FAILED,
                'second_opinion_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Agent subagent path — push critique findings into an open review.
     *
     * @param  list<array{severity?: string, body: string, area?: array<string, float>|null, screenshot_index?: int, related_pin?: int|null}>  $items
     * @return list<Finding>
     */
    public function addAgentFindings(Review $review, array $items): array
    {
        if (! $review->isOpenForFeedback()) {
            throw ValidationException::withMessages([
                'review' => 'This review is closed — start a fresh one if you need another pass.',
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'findings' => 'Include at least one finding.',
            ]);
        }

        if (count($items) > 20) {
            throw ValidationException::withMessages([
                'findings' => 'Keep it to 20 findings per call.',
            ]);
        }

        $review->loadMissing('screenshots');
        $created = [];

        foreach ($items as $item) {
            $index = (int) ($item['screenshot_index'] ?? 0);
            $shot = $review->screenshots->values()->get($index);

            if (! $shot) {
                throw ValidationException::withMessages([
                    'findings' => "No screenshot at index {$index}.",
                ]);
            }

            $severity = $item['severity'] ?? Finding::SEVERITY_SUGGESTION;
            if (! in_array($severity, [Finding::SEVERITY_SUGGESTION, Finding::SEVERITY_A11Y, Finding::SEVERITY_POLISH], true)) {
                throw ValidationException::withMessages([
                    'findings' => 'Finding severity must be suggestion, a11y, or polish.',
                ]);
            }

            $body = trim((string) ($item['body'] ?? ''));
            if ($body === '' || strlen($body) > 2000) {
                throw ValidationException::withMessages([
                    'findings' => 'Each finding needs a body (max 2000 chars).',
                ]);
            }

            $area = $this->normalizeArea($item['area'] ?? null);

            $created[] = $shot->findings()->create([
                'source' => Finding::SOURCE_AGENT,
                'severity' => $severity,
                'body' => $body,
                'area' => $area,
                'related_pin' => isset($item['related_pin']) ? (int) $item['related_pin'] : null,
            ]);
        }

        return $created;
    }

    public function requestForReview(Review $review, ?int $screenshotIndex = null): int
    {
        $review->loadMissing('screenshots');
        $shots = $review->screenshots->values();

        if ($screenshotIndex !== null) {
            $shot = $shots->get($screenshotIndex);
            if (! $shot) {
                throw ValidationException::withMessages([
                    'screenshot_index' => 'No screenshot at that index.',
                ]);
            }
            $this->queue($shot);

            return 1;
        }

        foreach ($shots as $shot) {
            $this->queue($shot);
        }

        return $shots->count();
    }

    /**
     * Free rule-based checklist, tuned to the review type. These are static
     * heuristics that never look at the pixels, so they carry no area — only
     * vision findings may point at a region.
     *
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function checklistFindings(Screenshot $screenshot): array
    {
        $review = $screenshot->review;
        $width = (int) ($screenshot->width ?: 0);
        $height = (int) ($screenshot->height ?: 0);
        $context = strtolower((string) ($review?->context ?? ''));
        $title = strtolower((string) ($review?->title ?? ''));
        $haystack = $context.' '.$title;

        $findings = match ($review?->type) {
            Review::TYPE_WEBSITE => $this->websiteChecklist($review, $width, $height, $haystack),
            Review::TYPE_PRESENTATION => $this->presentationChecklist(),
            Review::TYPE_EMAIL => $this->emailChecklist(),
            default => $this->uiChecklist($width, $height, $haystack),
        };

        if ($review?->page_url) {
            $findings[] = [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'A live page URL was provided — when implementing, ground these notes against the real DOM (roles/selectors), not the PNG alone.',
                'area' => null,
            ];
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function uiChecklist(int $width, int $height, string $haystack): array
    {
        $findings = [
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Check visual hierarchy: is there one clear primary action, or do multiple elements compete for attention?',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_A11Y,
                'body' => 'Verify text contrast on primary labels and buttons against their backgrounds (aim for WCAG AA).',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Scan spacing rhythm: look for uneven gaps between sections or cramped clusters near the edges.',
                'area' => null,
            ],
            // Emil Kowalski / design-engineering taste (static-frame heuristics)
            [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Taste check (emilkowalski/skills): primary pressables should feel responsive — confirm a clear pressed/active treatment (subtle scale ~0.97), not only a hover color swap.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Taste check: floating surfaces (cards, popovers, toolbars) often read better with soft depth (semi-transparent shadow/ring) than a hard opaque border — flag harsh boxes that fight the background.',
                'area' => null,
            ],
        ];

        if ($width > 0 && $height > 0) {
            $ratio = $width / max($height, 1);
            if ($ratio < 0.7) {
                $findings[] = [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'This shot looks mobile-tall — confirm tap targets are at least ~44×44px and primary actions sit in the thumb zone.',
                    'area' => null,
                ];
            } elseif ($ratio > 1.6) {
                $findings[] = [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Wide desktop frame — check that content doesn’t stretch edge-to-edge; confirm a readable measure for body text.',
                    'area' => null,
                ];
            }
        }

        if (str_contains($haystack, 'cta') || str_contains($haystack, 'button') || str_contains($haystack, 'signup') || str_contains($haystack, 'sign up')) {
            $findings[] = [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Context mentions a CTA — confirm the primary button is visually dominant and the label states the outcome, not a vague “Submit”.',
                'area' => null,
            ];
        }

        if (str_contains($haystack, 'nav') || str_contains($haystack, 'header') || str_contains($haystack, 'menu')) {
            $findings[] = [
                'severity' => Finding::SEVERITY_A11Y,
                'body' => 'Context mentions navigation — check focus order, link names, and that the current section is obvious without color alone.',
                'area' => null,
            ];
        }

        if (
            str_contains($haystack, 'animat')
            || str_contains($haystack, 'modal')
            || str_contains($haystack, 'drawer')
            || str_contains($haystack, 'toast')
            || str_contains($haystack, 'popover')
            || str_contains($haystack, 'dropdown')
            || str_contains($haystack, 'motion')
        ) {
            $findings[] = [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Motion context (emilkowalski/skills): prefer ease-out under ~300ms for UI chrome; never ease-in; don’t animate keyboard-triggered actions; popovers should scale from the trigger (modals stay centered).',
                'area' => null,
            ];
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function websiteChecklist(Review $review, int $width, int $height, string $haystack): array
    {
        $findings = [
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Above the fold: can a first-time visitor tell what this site offers and what to do next without scrolling?',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Navigation clarity: check that the primary nav labels are plain-language and the current section is obvious.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_A11Y,
                'body' => 'Verify heading structure and text contrast — hero text over imagery is a common WCAG AA failure.',
                'area' => null,
            ],
        ];

        if ($width > 0 && $height > 0) {
            $ratio = $width / max($height, 1);
            if ($ratio < 0.7) {
                $findings[] = [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Mobile viewport — confirm the hero still leads with the value proposition, tap targets are ~44×44px, and nothing overflows horizontally.',
                    'area' => null,
                ];
            } else {
                $findings[] = [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Desktop viewport — also review a mobile capture; most traffic will see the narrow breakpoint first.',
                    'area' => null,
                ];
            }
        }

        if (str_contains($haystack, 'cta') || str_contains($haystack, 'signup') || str_contains($haystack, 'sign up') || str_contains($haystack, 'pricing')) {
            $findings[] = [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Context mentions conversion — confirm one dominant CTA per view and that the label states the outcome.',
                'area' => null,
            ];
        }

        if ($review->page_url) {
            $findings[] = [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Live URL present — remember the parts a screenshot can’t show: title/meta description, Open Graph card, favicon, and load performance.',
                'area' => null,
            ];
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function presentationChecklist(): array
    {
        return [
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'One idea per slide: if the slide needs a paragraph to explain itself, it is probably two slides.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Text density: aim for roughly 6 lines / 6 words per line — the audience reads or listens, not both.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Consistency across slides: same title position, type scale, and color roles on every slide — drift reads as sloppiness.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_A11Y,
                'body' => 'Projection check: body text should stay readable from the back of a room (~24pt minimum) with strong contrast — thin light-gray type dies on projectors.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Charts and data: each chart should make one point, stated in the slide title — not just show data.',
                'area' => null,
            ],
        ];
    }

    /**
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function emailChecklist(): array
    {
        return [
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'One dominant CTA: emails convert on a single clear action — secondary links should visibly defer to it.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Subject and preheader: confirm both are written and the preheader complements (not repeats) the subject — the screenshot won’t show them.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Dark mode: many clients invert backgrounds — check logos on transparent PNGs, pure-black text, and borders that vanish when colors flip.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_A11Y,
                'body' => 'Images-off fallback: key content and the CTA must survive with images blocked — real text over background colors, alt text on every image.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_POLISH,
                'body' => 'Client rendering: keep the layout ~600px wide, table-based, single-column where possible — Outlook ignores most modern CSS.',
                'area' => null,
            ],
            [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Footer: confirm the unsubscribe link, physical address, and sender identity are present — legal requirements, not niceties.',
                'area' => null,
            ],
        ];
    }

    /**
     * The active vision provider, or null when none is configured.
     *
     * REVISEMY_VISION_PROVIDER forces one; the default "auto" prefers
     * Anthropic when its key is present, falling back to OpenAI so existing
     * OPENAI_API_KEY-only installs keep working unchanged.
     */
    protected function visionProvider(): ?VisionProvider
    {
        $configured = config('revisemy.vision.provider', 'auto');

        $provider = match ($configured) {
            'anthropic' => app(AnthropicVisionProvider::class),
            'openai' => app(OpenAiVisionProvider::class),
            'auto', null, '' => collect([
                app(AnthropicVisionProvider::class),
                app(OpenAiVisionProvider::class),
            ])->first(fn (VisionProvider $p) => $p->enabled()),
            default => null,
        };

        return $provider?->enabled() ? $provider : null;
    }

    /**
     * Vision prompt: shared contract + a taste lens tuned to the review type.
     */
    protected function visionPrompt(?Review $review): string
    {
        $context = trim((string) ($review?->context ?? ''));
        $title = (string) ($review?->title ?? 'UI review');
        $subject = $review?->typeLabel() ?? 'UI';

        $lens = match ($review?->type) {
            Review::TYPE_WEBSITE => <<<'LENS'
Taste lens (marketing/content website):
- Above the fold: value proposition and next step must be clear without scrolling.
- One dominant CTA per view; the label states the outcome.
- Navigation: plain-language labels, obvious current section, no mystery-meat icons.
- Typography: readable measure (~45–75 chars), clear heading hierarchy.
- Trust: consistent brand color roles; flag hero text with weak contrast over imagery.
- If this looks like a narrow/mobile capture, check tap targets and horizontal overflow.
LENS,
            Review::TYPE_PRESENTATION => <<<'LENS'
Taste lens (presentation slide):
- One idea per slide; the title should state the point, not just the topic.
- Text density: flag walls of text (~6 lines / 6 words per line is a good ceiling).
- Readability at distance: small or thin low-contrast type dies on projectors.
- Alignment and consistency: title position, type scale, color roles should look systematic.
- Charts: each should make one point; flag chart junk, unreadable labels, rainbow palettes.
LENS,
            Review::TYPE_EMAIL => <<<'LENS'
Taste lens (HTML email):
- One dominant CTA; secondary links visibly defer to it.
- Dark-mode risk: transparent-logo halos, pure-black text, borders that vanish on inverted backgrounds.
- Images-off resilience: key copy and the CTA should be real text, not baked into images.
- Client constraints: ~600px width, single column preferred; flag layouts that need modern CSS to survive Outlook.
- Footer: unsubscribe, physical address, sender identity present.
LENS,
            default => <<<'LENS'
Taste lens (Emil Kowalski design-engineering / emilkowalski/skills — apply when visible in the still):
- Hierarchy & restraint: one clear primary action; avoid competing chrome.
- Pressables: look for missing pressed/active affordance; hover-only feedback is weak.
- Depth: soft shadow/ring often beats hard opaque borders on floating surfaces.
- Motion (if UI implies modals/drawers/toasts/popovers): ease-out, under ~300ms for chrome; never ease-in; no motion on keyboard-triggered actions; popovers origin from trigger (modals centered); never scale(0) — use ~0.95 + opacity.
- Accessibility: contrast, focus visibility, reduced-motion awareness when motion is implied.
- Prefer concrete CSS/layout fixes in the body when possible.
LENS,
        };

        return <<<PROMPT
You are a design-reviewer subagent for ReviseMy. Critique this {$subject} screenshot.
Return ONLY valid JSON: {"findings":[{"severity":"suggestion|a11y|polish","body":"string","area":{"x":0-1,"y":0-1,"w":0-1,"h":0-1}|null}]}
Rules:
- Max 6 findings. Be specific and actionable.
- severity must be suggestion, a11y, or polish — never must-fix.
- area is normalized 0–1 relative to the image; null if global.
- Do not approve or reject the design; suggestions only.
- Human marks stay authoritative; you only hint.

{$lens}

Title: {$title}
Context: {$context}
PROMPT;
    }

    /**
     * @param  list<array{severity: string, body: string, area: array<string, float>|null}>  $candidates
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    protected function dedupeAgainstExisting(Screenshot $screenshot, array $candidates): array
    {
        $existingBodies = $screenshot->findings->pluck('body')->map(fn ($b) => strtolower((string) $b))->all();

        return array_values(array_filter($candidates, function (array $item) use ($existingBodies) {
            $body = strtolower($item['body']);
            foreach ($existingBodies as $existing) {
                similar_text($body, $existing, $percent);
                if ($percent > 72) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  list<array{severity: string, body: string, area: array<string, float>|null}>  $items
     */
    protected function persistFindings(Screenshot $screenshot, array $items, string $source): void
    {
        foreach ($items as $item) {
            $screenshot->findings()->create([
                'source' => $source,
                'severity' => $item['severity'],
                'body' => $item['body'],
                'area' => $item['area'],
                'related_pin' => null,
            ]);
        }
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    protected function normalizeArea(mixed $area): ?array
    {
        if (! is_array($area)) {
            return null;
        }

        $x = max(0, min(1, (float) ($area['x'] ?? 0)));
        $y = max(0, min(1, (float) ($area['y'] ?? 0)));
        $w = max(0, min(1 - $x, (float) ($area['w'] ?? 0)));
        $h = max(0, min(1 - $y, (float) ($area['h'] ?? 0)));

        if ($w < 0.02 || $h < 0.02) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }
}
