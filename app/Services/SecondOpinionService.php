<?php

namespace App\Services;

use App\Jobs\GenerateSecondOpinionJob;
use App\Models\Finding;
use App\Models\Review;
use App\Models\Screenshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
                ->whereIn('source', [Finding::SOURCE_CHECKLIST, Finding::SOURCE_OPENAI])
                ->delete();

            $checklist = $this->checklistFindings($screenshot);
            $this->persistFindings($screenshot, $checklist, Finding::SOURCE_CHECKLIST);

            if ($this->openaiKey()) {
                $vision = $this->openaiFindings($screenshot);
                $merged = $this->dedupeAgainstExisting($screenshot->fresh('findings'), $vision);
                $this->persistFindings($screenshot, $merged, Finding::SOURCE_OPENAI);
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

        $findings = [];

        $findings[] = [
            'severity' => Finding::SEVERITY_SUGGESTION,
            'body' => 'Check visual hierarchy: is there one clear primary action, or do multiple elements compete for attention?',
            'area' => ['x' => 0.08, 'y' => 0.08, 'w' => 0.84, 'h' => 0.18],
        ];

        $findings[] = [
            'severity' => Finding::SEVERITY_A11Y,
            'body' => 'Verify text contrast on primary labels and buttons against their backgrounds (aim for WCAG AA).',
            'area' => ['x' => 0.12, 'y' => 0.55, 'w' => 0.4, 'h' => 0.12],
        ];

        $findings[] = [
            'severity' => Finding::SEVERITY_POLISH,
            'body' => 'Scan spacing rhythm: look for uneven gaps between sections or cramped clusters near the edges.',
            'area' => ['x' => 0.05, 'y' => 0.28, 'w' => 0.9, 'h' => 0.2],
        ];

        // Emil Kowalski / design-engineering taste (static-frame heuristics)
        $findings[] = [
            'severity' => Finding::SEVERITY_POLISH,
            'body' => 'Taste check (emilkowalski/skills): primary pressables should feel responsive — confirm a clear pressed/active treatment (subtle scale ~0.97), not only a hover color swap.',
            'area' => ['x' => 0.28, 'y' => 0.58, 'w' => 0.44, 'h' => 0.14],
        ];

        $findings[] = [
            'severity' => Finding::SEVERITY_POLISH,
            'body' => 'Taste check: floating surfaces (cards, popovers, toolbars) often read better with soft depth (semi-transparent shadow/ring) than a hard opaque border — flag harsh boxes that fight the background.',
            'area' => ['x' => 0.12, 'y' => 0.18, 'w' => 0.76, 'h' => 0.28],
        ];

        if ($width > 0 && $height > 0) {
            $ratio = $width / max($height, 1);
            if ($ratio < 0.7) {
                $findings[] = [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'This shot looks mobile-tall — confirm tap targets are at least ~44×44px and primary actions sit in the thumb zone.',
                    'area' => ['x' => 0.2, 'y' => 0.78, 'w' => 0.6, 'h' => 0.14],
                ];
            } elseif ($ratio > 1.6) {
                $findings[] = [
                    'severity' => Finding::SEVERITY_SUGGESTION,
                    'body' => 'Wide desktop frame — check that content doesn’t stretch edge-to-edge; confirm a readable measure for body text.',
                    'area' => ['x' => 0.15, 'y' => 0.2, 'w' => 0.7, 'h' => 0.35],
                ];
            }
        }

        if (str_contains($haystack, 'cta') || str_contains($haystack, 'button') || str_contains($haystack, 'signup') || str_contains($haystack, 'sign up')) {
            $findings[] = [
                'severity' => Finding::SEVERITY_SUGGESTION,
                'body' => 'Context mentions a CTA — confirm the primary button is visually dominant and the label states the outcome, not a vague “Submit”.',
                'area' => ['x' => 0.3, 'y' => 0.62, 'w' => 0.4, 'h' => 0.12],
            ];
        }

        if (str_contains($haystack, 'nav') || str_contains($haystack, 'header') || str_contains($haystack, 'menu')) {
            $findings[] = [
                'severity' => Finding::SEVERITY_A11Y,
                'body' => 'Context mentions navigation — check focus order, link names, and that the current section is obvious without color alone.',
                'area' => ['x' => 0.05, 'y' => 0.02, 'w' => 0.9, 'h' => 0.1],
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
    protected function openaiFindings(Screenshot $screenshot): array
    {
        $key = $this->openaiKey();
        if (! $key) {
            return [];
        }

        $binary = Storage::disk($screenshot->disk)->get($screenshot->path);
        if ($binary === null || $binary === '') {
            return [];
        }

        $mime = match (strtolower(pathinfo($screenshot->path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };

        $dataUrl = 'data:'.$mime.';base64,'.base64_encode($binary);
        $review = $screenshot->review;
        $context = trim((string) ($review?->context ?? ''));
        $title = (string) ($review?->title ?? 'UI review');

        $prompt = <<<PROMPT
You are a design-reviewer subagent for ReviseMy. Critique this UI screenshot.
Return ONLY valid JSON: {"findings":[{"severity":"suggestion|a11y|polish","body":"string","area":{"x":0-1,"y":0-1,"w":0-1,"h":0-1}|null}]}
Rules:
- Max 6 findings. Be specific and actionable.
- severity must be suggestion, a11y, or polish — never must-fix.
- area is normalized 0–1 relative to the image; null if global.
- Do not approve or reject the design; suggestions only.
- Human marks stay authoritative; you only hint.

Taste lens (Emil Kowalski design-engineering / emilkowalski/skills — apply when visible in the still):
- Hierarchy & restraint: one clear primary action; avoid competing chrome.
- Pressables: look for missing pressed/active affordance; hover-only feedback is weak.
- Depth: soft shadow/ring often beats hard opaque borders on floating surfaces.
- Motion (if UI implies modals/drawers/toasts/popovers): ease-out, under ~300ms for chrome; never ease-in; no motion on keyboard-triggered actions; popovers origin from trigger (modals centered); never scale(0) — use ~0.95 + opacity.
- Accessibility: contrast, focus visibility, reduced-motion awareness when motion is implied.
- Prefer concrete CSS/layout fixes in the body when possible.

Title: {$title}
Context: {$context}
PROMPT;

        $response = Http::withToken($key)
            ->timeout((int) config('revisemy.openai.timeout', 45))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('revisemy.openai.model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI vision request failed: '.$response->status());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;
        $raw = is_array($decoded) ? ($decoded['findings'] ?? []) : [];

        $out = [];
        foreach (array_slice($raw, 0, 6) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $severity = $item['severity'] ?? Finding::SEVERITY_SUGGESTION;
            if (! in_array($severity, [Finding::SEVERITY_SUGGESTION, Finding::SEVERITY_A11Y, Finding::SEVERITY_POLISH], true)) {
                $severity = Finding::SEVERITY_SUGGESTION;
            }
            $body = trim((string) ($item['body'] ?? ''));
            if ($body === '') {
                continue;
            }
            $out[] = [
                'severity' => $severity,
                'body' => $body,
                'area' => $this->normalizeArea($item['area'] ?? null),
            ];
        }

        return $out;
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
     * @param  mixed  $area
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

    protected function openaiKey(): ?string
    {
        $key = config('revisemy.openai.api_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
