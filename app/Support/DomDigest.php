<?php

namespace App\Support;

/**
 * Lossy DOM cleanup for AI prompts: strips everything a vision reviewer
 * can't use (scripts, styles, svg paths, data URIs) and truncates to a
 * token budget. Not a parser — regex is fine because the output is context,
 * never rendered or round-tripped.
 */
class DomDigest
{
    public static function clean(string $html, int $maxChars = 12000): string
    {
        $patterns = [
            '#<script\b[^>]*>.*?</script>#is' => '',
            '#<style\b[^>]*>.*?</style>#is' => '',
            '#<noscript\b[^>]*>.*?</noscript>#is' => '',
            '#<template\b[^>]*>.*?</template>#is' => '',
            '#<!--.*?-->#s' => '',
            '#(<svg\b[^>]*>).*?(</svg>)#is' => '$1$2',
            '#<link\b[^>]*>#i' => '',
            '#\bstyle\s*=\s*("[^"]*"|\'[^\']*\')#i' => '',
            '#\b(?:src|href|xlink:href)\s*=\s*("data:[^"]*"|\'data:[^\']*\')#i' => '',
        ];

        $cleaned = (string) preg_replace(array_keys($patterns), array_values($patterns), $html);
        $cleaned = trim((string) preg_replace('#\s{2,}#', ' ', $cleaned));

        if (strlen($cleaned) > $maxChars) {
            $cleaned = substr($cleaned, 0, $maxChars).'<!-- truncated -->';
        }

        return $cleaned;
    }
}
