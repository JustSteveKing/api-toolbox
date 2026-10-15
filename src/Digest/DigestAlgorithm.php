<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest;

/**
 * The hashing algorithms RFC 9530 section 7.2 registers as Active, the only ones it recommends.
 *
 * The Deprecated ones (md5, sha, unixsum, unixcksum, adler, crc32c) are not supported: section 5
 * says they MUST NOT be used where an attacker could be involved, and a public API is such a place.
 */
enum DigestAlgorithm: string
{
    case Sha256 = 'sha-256';
    case Sha512 = 'sha-512';

    public function digest(string $content): string
    {
        return hash($this->hashName(), $content, true);
    }

    /**
     * The digest length in bytes, so a value of any other length can be refused unhashed, as the
     * digest problem types draft's invalid-values type describes.
     */
    public function length(): int
    {
        return match ($this) {
            self::Sha256 => 32,
            self::Sha512 => 64,
        };
    }

    private function hashName(): string
    {
        return match ($this) {
            self::Sha256 => 'sha256',
            self::Sha512 => 'sha512',
        };
    }
}
