<?php

namespace App\Support;

use Laravel\Passport\Passport;

/**
 * The key pair connector tokens are signed and checked with.
 *
 * Passport signs a token with the private key and checks it with the public
 * one. On 2026-10-10 production handed Claude a token and then turned down
 * every call made with it: the public key it had did not check what the
 * private key signed, and nothing said so. The public key is now worked out
 * from the private key, so the two can't drift apart. PASSPORT_PUBLIC_KEY is
 * no longer needed; when it's set and doesn't match, it is ignored.
 *
 * Laravel Cloud keeps one KEY=value per line, so a key is pasted as a single
 * line with \n where the line breaks go. Passport reads it the same way.
 */
class PassportKeys
{
    /** @var array<string, string|null> Public keys already worked out, by private key hash. */
    private static array $derived = [];

    /** @var array{0: mixed}|null What PASSPORT_PUBLIC_KEY held before apply() replaced it. */
    private static ?array $publicAsSet = null;

    /** The private key's PEM, from PASSPORT_PRIVATE_KEY or storage/oauth-private.key. */
    public static function privatePem(): ?string
    {
        return self::pem(config('passport.private_key'), 'oauth-private.key');
    }

    /** The public key someone set, from PASSPORT_PUBLIC_KEY or storage/oauth-public.key. */
    public static function configuredPublicPem(): ?string
    {
        // apply() replaces passport.public_key for Passport; keep reading what was set.
        return self::pem(self::$publicAsSet !== null ? self::$publicAsSet[0] : config('passport.public_key'), 'oauth-public.key');
    }

    /** The public key that checks what the private key signs, or null with no usable private key. */
    public static function publicPem(): ?string
    {
        $private = self::privatePem();

        if ($private === null) {
            return null;
        }

        $hash = hash('sha256', $private);

        if (! array_key_exists($hash, self::$derived)) {
            $key = @openssl_pkey_get_private($private);
            $details = $key === false ? false : openssl_pkey_get_details($key);

            self::$derived[$hash] = is_array($details) && is_string($details['key'] ?? null) ? $details['key'] : null;
        }

        return self::$derived[$hash];
    }

    /** Point Passport at the matching public key, just before it builds the token checker. */
    public static function apply(): void
    {
        $public = self::publicPem();

        if ($public !== null && config('passport.public_key') !== $public) {
            self::$publicAsSet ??= [config('passport.public_key')];

            config(['passport.public_key' => $public]);
        }
    }

    /** Whether tokens can be both signed and checked. */
    public static function ready(): bool
    {
        return self::publicPem() !== null;
    }

    /** Sign something with the private key and check it with the public key Passport will use. */
    public static function roundTrip(): bool
    {
        $private = self::privatePem();
        $public = self::publicPem();

        if ($private === null || $public === null) {
            return false;
        }

        $data = 'revisemy-key-check:'.bin2hex(random_bytes(8));

        return @openssl_sign($data, $signature, $private, OPENSSL_ALGO_SHA256)
            && openssl_verify($data, $signature, $public, OPENSSL_ALGO_SHA256) === 1;
    }

    /** A public key is set, but it isn't the private key's, so it would have turned every token down. */
    public static function configuredPublicMismatch(): bool
    {
        $configured = self::configuredPublicPem();
        $derived = self::publicPem();

        if ($configured === null || $derived === null || $configured === $derived) {
            return false;
        }

        $key = @openssl_pkey_get_public($configured);
        $details = $key === false ? false : openssl_pkey_get_details($key);

        return ! is_array($details) || ($details['key'] ?? null) !== $derived;
    }

    /** Forget worked-out keys, for tests that swap keys. */
    public static function flush(): void
    {
        self::$derived = [];
        self::$publicAsSet = null;
    }

    private static function pem(mixed $value, string $file): ?string
    {
        $value = is_string($value) ? trim(str_replace('\\n', "\n", $value)) : '';

        if ($value !== '') {
            return $value;
        }

        $path = Passport::keyPath($file);

        return is_file($path) && is_readable($path) ? (string) file_get_contents($path) : null;
    }
}
