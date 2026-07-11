<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Light hygiene for guest names and mark/comment bodies — not a content-moderation stack.
 */
class FeedbackText
{
    /**
     * Letters, numbers, spaces, and a few name punctuation marks. No newlines or emoji.
     */
    public const NAME_PATTERN = '/^[\p{L}\p{N} .\'\-]+$/u';

    public static function sanitizeName(?string $value): string
    {
        $value = self::stripControls((string) $value, allowNewlines: false);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    public static function sanitizeBody(?string $value): string
    {
        $value = self::stripControls((string) $value, allowNewlines: true);
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? '';

        return trim($value);
    }

    /**
     * @return list<string>
     */
    public static function nameRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'max:40',
            'regex:'.self::NAME_PATTERN,
        ];
    }

    /**
     * @return list<string>
     */
    public static function bodyRules(int $max = 2000): array
    {
        return ['required', 'string', 'max:'.$max];
    }

    /**
     * @return array<string, string>
     */
    public static function nameMessages(string $attribute = 'guestName'): array
    {
        return [
            "{$attribute}.required" => 'Add your name so everyone knows who commented.',
            "{$attribute}.regex" => 'Use a short name with letters, numbers, spaces, or . \' -',
            "{$attribute}.max" => 'Keep names under 40 characters.',
        ];
    }

    /**
     * Cap guest mark/comment spam on a share link (per review + IP).
     */
    public static function throttleGuest(int $reviewId, int $maxAttempts = 20): void
    {
        $key = sprintf('guest-feedback:%d:%s', $reviewId, request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'guestName' => 'Too many submissions — wait a minute and try again.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }

    protected static function stripControls(string $value, bool $allowNewlines): string
    {
        if ($allowNewlines) {
            // Drop control chars except tab / newline / carriage return.
            return preg_replace('/[^\P{C}\t\n\r]/u', '', $value) ?? '';
        }

        return preg_replace('/\p{C}+/u', '', $value) ?? '';
    }
}
