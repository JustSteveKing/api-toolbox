<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use InvalidArgumentException;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;

/**
 * Keys from the feature's "keys" option:
 *
 *     'keys' => [
 *         'webhooks' => ['algorithm' => 'hmac-sha256', 'secret' => env('WEBHOOK_SECRET')],
 *         'partner' => ['algorithm' => 'ed25519', 'public_key' => storage_path('keys/partner.pem')],
 *     ],
 *
 * A secret prefixed "base64:" is decoded, as Laravel's APP_KEY is. A key given as a path to a
 * readable file is read from it.
 */
final readonly class ConfiguredKeys implements KeyResolver
{
    /**
     * @param  array<array-key, mixed>  $keys
     */
    public function __construct(
        private array $keys,
    ) {}

    public function resolve(string $keyId): ?Key
    {
        $key = $this->keys[$keyId] ?? null;

        if (! is_array($key)) {
            return null;
        }

        $algorithm = $key['algorithm'] ?? null;

        if (! is_string($algorithm)) {
            throw new InvalidArgumentException("Key [{$keyId}] needs an algorithm.");
        }

        return new Key(
            $keyId,
            $algorithm,
            self::secret($key['secret'] ?? null),
            self::material($key['private_key'] ?? null),
            self::material($key['public_key'] ?? null),
        );
    }

    private static function secret(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return str_starts_with($value, 'base64:') ? (base64_decode(substr($value, 7), true) ?: null) : $value;
    }

    private static function material(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return ! str_contains($value, "\n") && is_file($value) ? (string) file_get_contents($value) : $value;
    }
}
