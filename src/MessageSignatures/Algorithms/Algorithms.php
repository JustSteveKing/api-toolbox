<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;

/**
 * The algorithms from the HTTP Signature Algorithms registry (RFC 9421, section 6.2.2). JSON Web
 * Signature algorithms (section 3.3.7) are not included.
 */
final class Algorithms
{
    public static function named(string $name): SignatureAlgorithm
    {
        return match ($name) {
            'hmac-sha256' => new HmacSha256,
            'ed25519' => new Ed25519,
            'ecdsa-p256-sha256' => Ecdsa::p256(),
            'ecdsa-p384-sha384' => Ecdsa::p384(),
            'rsa-v1_5-sha256' => new RsaV15Sha256,
            'rsa-pss-sha512' => new RsaPssSha512,
            default => throw new KeyException("[{$name}] is not an algorithm in the HTTP Signature Algorithms registry."),
        };
    }
}
