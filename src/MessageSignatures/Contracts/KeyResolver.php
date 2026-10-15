<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Contracts;

use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * Turns a keyid into key material (RFC 9421, section 3.2, step 5). Return null for a key that is
 * not known or not trusted for this request; verification then fails, as that step requires.
 *
 * The default reads keys from config. Bind your own to look them up per client, from a database
 * or a JWKS endpoint.
 */
interface KeyResolver
{
    public function resolve(string $keyId): ?Key;
}
