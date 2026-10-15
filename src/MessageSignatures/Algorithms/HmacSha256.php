<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * RFC 9421 section 3.3.3. Compared in constant time.
 */
final class HmacSha256 implements SignatureAlgorithm
{
    public function name(): string
    {
        return 'hmac-sha256';
    }

    public function sign(string $base, Key $key): string
    {
        return hash_hmac('sha256', $base, $key->secret ?? throw KeyException::missing($key, 'secret'), true);
    }

    public function verify(string $base, string $signature, Key $key): bool
    {
        return hash_equals($this->sign($base, $key), $signature);
    }
}
