<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Exceptions;

use JustSteveKing\Toolbox\MessageSignatures\Key;
use RuntimeException;

/**
 * A configuration problem with key material, not a bad signature: it is the application's to fix.
 */
final class KeyException extends RuntimeException
{
    public static function missing(Key $key, string $what): self
    {
        return new self("Key [{$key->id}] ({$key->algorithm}) needs a {$what}.");
    }

    public static function unreadable(Key $key): self
    {
        return new self("Key [{$key->id}] could not be used for {$key->algorithm}: ".(openssl_error_string() ?: 'unreadable key material').'.');
    }
}
