<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class PdfPageMatch
{
    public static function pairs(Collection $current, Collection $previous): array
    {
        $signatures = fn (Collection $shots) => $shots->filter->isPdfPage()->mapWithKeys(function ($shot) {
            $words = self::words($shot->meta['pdf_text'] ?? '');
            $text = implode(' ', $words);
            if (count($words) > 500) {
                $words = array_merge(array_slice($words, 0, 350), array_slice($words, -150));
            }
            $pairs = [];
            for ($i = 1; $i < count($words); $i++) {
                $pairs[$words[$i - 1].' '.$words[$i]] = true;
            }

            return [$shot->id => ['text' => $text, 'pairs' => $pairs]];
        });

        $old = $signatures($previous);
        $candidates = [];
        $reverse = [];
        foreach ($signatures($current) as $id => $page) {
            if ($page['text'] === '') {
                continue;
            }
            foreach ($old as $oldId => $before) {
                if ($before['text'] === '') {
                    continue;
                }
                $union = count($page['pairs'] + $before['pairs']);
                $score = $page['text'] === $before['text'] ? 1.0
                    : ($union > 0 ? count(array_intersect_key($page['pairs'], $before['pairs'])) / $union : 0.0);
                if ($score >= 0.35) {
                    $candidates[$id][$oldId] = $score;
                    $reverse[$oldId][$id] = $score;
                }
            }
        }

        $best = static function (array $scores): ?int {
            arsort($scores);
            $values = array_values($scores);

            return ($values[0] - ($values[1] ?? 0)) >= 0.12 ? array_key_first($scores) : null;
        };

        $matches = [];
        foreach ($candidates as $id => $scores) {
            $oldId = $best($scores);
            if ($oldId !== null && $best($reverse[$oldId]) === $id) {
                $matches[$id] = $oldId;
            }
        }

        return $matches;
    }

    public static function changes(string $old, string $new): ?array
    {
        if ($old === $new) {
            return null;
        }
        $before = explode(' ', $old);
        $after = explode(' ', $new);
        $first = 0;
        $end = 0;
        $limit = min(count($before), count($after));
        while ($first < $limit && $before[$first] === $after[$first]) {
            $first++;
        }
        while ($end < $limit - $first && $before[count($before) - $end - 1] === $after[count($after) - $end - 1]) {
            $end++;
        }
        $excerpt = fn (array $words) => Str::limit(implode(' ', array_slice($words, $first, count($words) - $first - $end)), 600);

        return ['before' => $excerpt($before), 'after' => $excerpt($after)];
    }

    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
