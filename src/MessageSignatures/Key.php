<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

/**
 * Key material and the one algorithm it is used with.
 *
 * The algorithm belongs to the key, not to the message. RFC 9421 section 7.3.6 describes the
 * downgrade an attacker gets by choosing the algorithm through the alg parameter, such as asking
 * for HMAC so a public key is used as the secret. Binding it here closes that.
 *
 * $secret is raw bytes for hmac-sha256. $privateKey and $publicKey are PEM, or for ed25519 also
 * raw sodium keys (64-byte secret, 32-byte public).
 */
final readonly class Key
{
    public function __construct(
        public string $id,
        public string $algorithm,
        public ?string $secret = null,
        public ?string $privateKey = null,
        public ?string $publicKey = null,
    ) {}
}
