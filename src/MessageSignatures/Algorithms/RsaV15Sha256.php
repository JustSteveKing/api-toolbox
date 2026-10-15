<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * RFC 9421 section 3.3.2: RSASSA-PKCS1-v1_5 with SHA-256.
 */
final class RsaV15Sha256 implements SignatureAlgorithm
{
    public function name(): string
    {
        return 'rsa-v1_5-sha256';
    }

    public function sign(string $base, Key $key): string
    {
        $material = openssl_pkey_get_private($key->privateKey ?? throw KeyException::missing($key, 'private_key'))
            ?: throw KeyException::unreadable($key);

        if (! openssl_sign($base, $signature, $material, OPENSSL_ALGO_SHA256) || ! is_string($signature)) {
            throw KeyException::unreadable($key);
        }

        return $signature;
    }

    public function verify(string $base, string $signature, Key $key): bool
    {
        $material = openssl_pkey_get_public($key->publicKey ?? throw KeyException::missing($key, 'public_key'))
            ?: throw KeyException::unreadable($key);

        return openssl_verify($base, $signature, $material, OPENSSL_ALGO_SHA256) === 1;
    }
}
