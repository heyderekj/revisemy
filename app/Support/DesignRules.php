<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A project's own design rules, as its DESIGN.md says them.
 *
 * DESIGN.md is a Markdown file in a repo's root that tells coding agents the
 * design system: tokens, type, spacing, components, and what to avoid. It
 * says the rules before the agent builds; nothing checked the result against
 * them. ReviseMy does: the agent passes the file with create_review (it can
 * read the repo, we can't), and the second opinion checks each shot against
 * those rules first. There's no formal spec, so this reads any Markdown and
 * pulls out the lines that read like rules.
 */
class DesignRules
{
    /** About 5,000 words: room for any real DESIGN.md, not for a whole wiki. */
    public const MAX_LENGTH = 20_000;

    /** What the vision prompt carries, so the shots keep most of the budget. */
    public const PROMPT_LENGTH = 8_000;

    /** How many rules the free checklist turns into checks. */
    public const CHECKLIST_RULES = 4;

    public const SOURCE_AGENT = 'agent';

    public const SOURCE_WORKSPACE = 'workspace';

    /** Words that mark a line as a rule worth checking first. */
    private const FIRM = ['never', 'always', 'must', 'don’t', "don't", 'do not', 'avoid', 'only', 'no '];

    /** Normalised, capped, or null when there's nothing there. */
    public static function clean(?string $markdown): ?string
    {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", (string) $markdown));

        return $markdown === '' ? null : mb_substr($markdown, 0, self::MAX_LENGTH);
    }

    /** The first heading, e.g. "Acme design system". */
    public static function title(?string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', (string) $markdown, $match)) {
            return Str::limit(trim($match[1]), 80);
        }

        return null;
    }

    /**
     * The lines that read like rules: list items and table rows, firm ones
     * (never, always, must, avoid…) first.
     *
     * @return list<string>
     */
    public static function rules(?string $markdown): array
    {
        $rules = [];

        foreach (explode("\n", (string) $markdown) as $line) {
            $line = trim($line);

            if (preg_match('/^(?:[-*+]|\d+[.)])\s+(.+)$/u', $line, $match)) {
                $rule = $match[1];
            } elseif (str_starts_with($line, '|') && ! preg_match('/^\|[\s:|-]+\|?$/', $line)) {
                $cells = array_values(array_filter(array_map('trim', explode('|', trim($line, '|'))), fn ($cell) => $cell !== ''));
                $rule = count($cells) >= 2 ? implode(': ', $cells) : '';
            } else {
                continue;
            }

            // Bold, code ticks and links read as clutter in a hint.
            $rule = trim((string) preg_replace(['/\*\*|__|`/', '/\[([^\]]+)\]\([^)]+\)/'], ['', '$1'], $rule));

            if (mb_strlen($rule) >= 8) {
                $rules[] = Str::limit($rule, 200);
            }
        }

        $rules = array_values(array_unique($rules));
        $firm = array_values(array_filter($rules, fn ($rule) => self::isFirm($rule)));
        $rest = array_values(array_filter($rules, fn ($rule) => ! self::isFirm($rule)));

        return array_slice([...$firm, ...$rest], 0, 40);
    }

    /**
     * The free checklist's share: the firmest rules, as things to check.
     *
     * @return list<array{severity: string, body: string, area: null}>
     */
    public static function checklist(?string $markdown): array
    {
        return array_map(fn (string $rule) => [
            'severity' => 'suggestion',
            'body' => 'DESIGN.md: '.$rule,
            'area' => null,
        ], array_slice(self::rules($markdown), 0, self::CHECKLIST_RULES));
    }

    /**
     * The one line both the web review and the inline review show on the
     * DESIGN.md chip. Keep the two in step by using this, and the payload's
     * design_rules for the inline one.
     */
    public static function summary(int $ruleCount, ?string $title, ?string $source): string
    {
        $from = $title !== null ? "“{$title}”" : 'your DESIGN.md';
        $who = $source === self::SOURCE_WORKSPACE ? 'your saved rules' : 'what your agent sent';

        return "Hints check against {$ruleCount} ".($ruleCount === 1 ? 'rule' : 'rules')." from {$from} ({$who}).";
    }

    /** The rules as the vision model sees them: fenced, labelled as data. */
    public static function forPrompt(?string $markdown): string
    {
        $markdown = mb_substr((string) $markdown, 0, self::PROMPT_LENGTH);

        // The fence can't be closed from inside the file.
        return str_replace('```', "'''", $markdown);
    }

    private static function isFirm(string $rule): bool
    {
        $lower = ' '.mb_strtolower($rule);

        foreach (self::FIRM as $word) {
            if (str_contains($lower, ' '.$word)) {
                return true;
            }
        }

        return false;
    }
}
