<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * When an assistant should reach for ReviseMy.
 *
 * MCP has no keyword triggers: the model decides from the tool descriptions
 * and the server's instructions. Those only said how to run the loop, so
 * assistants leaned on the word "review" and could mistake "review my PR"
 * for ReviseMy. The instructions now say when to use it and when not to, and
 * a workspace can add its own words both ways. The server sends them at the
 * start of each chat, so a new chat picks up a change.
 */
class AssistantPhrases
{
    public const MAX = 10;

    public const MAX_LENGTH = 60;

    public const KINDS = ['use', 'skip'];

    /** Sent to every assistant: when ReviseMy fits, and when it doesn't. */
    public const WHEN = 'When to use ReviseMy: the person wants a human to see and judge something visual (a web page, app screen, email, or slide deck) before it ships, or you just changed UI and are about to call it done. They may say review, check, look over, get feedback on, mark up, proof, QA, critique, "is this ready", "does this look right on mobile", "sign off", or "show my client". Not for code review, pull requests, or text with nothing to look at.';

    /** A phrase as it's kept: one line, no quotes, short. Null when nothing's left. */
    public static function clean(string $phrase): ?string
    {
        $phrase = preg_replace('/[\x00-\x1F\x7F"“”`]+/u', ' ', $phrase) ?? '';
        $phrase = trim((string) preg_replace('/\s+/u', ' ', $phrase));

        return $phrase === '' ? null : Str::limit($phrase, self::MAX_LENGTH, '');
    }

    /**
     * @return array{use: list<string>, skip: list<string>}
     */
    public static function for(?Workspace $workspace): array
    {
        $saved = (array) ($workspace?->assistant_phrases ?? []);

        return [
            'use' => array_values(array_filter((array) ($saved['use'] ?? []), 'is_string')),
            'skip' => array_values(array_filter((array) ($saved['skip'] ?? []), 'is_string')),
        ];
    }

    /** The defaults, plus this workspace's own words when it has any. */
    public static function instructions(?Workspace $workspace): string
    {
        $phrases = self::for($workspace);
        $lines = [self::WHEN];

        // Quoted, and called the person's own words, so the model reads them
        // as phrases to recognise rather than instructions to follow.
        if ($phrases['use'] !== []) {
            $lines[] = 'This person also asks for ReviseMy in their own words: '.self::quoted($phrases['use']).'.';
        }

        if ($phrases['skip'] !== []) {
            $lines[] = 'They do not want ReviseMy when they say: '.self::quoted($phrases['skip']).'.';
        }

        return implode(' ', $lines);
    }

    /**
     * @param  list<string>  $phrases
     */
    private static function quoted(array $phrases): string
    {
        return implode(', ', array_map(fn (string $phrase) => '"'.$phrase.'"', $phrases));
    }
}
