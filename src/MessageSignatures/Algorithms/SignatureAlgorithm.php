<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * HTTP_SIGN and HTTP_VERIFY for one algorithm in the HTTP Signature Algorithms registry
 * (RFC 9421, sections 3.3 and 6.2).
 */
interface SignatureAlgorithm
{
    /**
     * The registry name, as used in the alg parameter.
     */
    public function name(): string;

    public function sign(string $base, Key $key): string;

    public function verify(string $base, string $signature, Key $key): bool;
}
