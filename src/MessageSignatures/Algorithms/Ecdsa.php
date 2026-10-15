<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * RFC 9421 sections 3.3.4 and 3.3.5. The signature is r and s as fixed-width big-endian integers,
 * concatenated: 64 bytes for P-256, 96 for P-384. OpenSSL works in DER, so values are converted
 * both ways.
 */
final readonly class Ecdsa implements SignatureAlgorithm
{
    /**
     * @param  positive-int  $width  Bytes in each of r and s.
     */
    private function __construct(
        private string $name,
        private int $digest,
        private int $width,
    ) {}

    public static function p256(): self
    {
        return new self('ecdsa-p256-sha256', OPENSSL_ALGO_SHA256, 32);
    }

    public static function p384(): self
    {
        return new self('ecdsa-p384-sha384', OPENSSL_ALGO_SHA384, 48);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function sign(string $base, Key $key): string
    {
        $material = openssl_pkey_get_private($key->privateKey ?? throw KeyException::missing($key, 'private_key'))
            ?: throw KeyException::unreadable($key);

        if (! openssl_sign($base, $der, $material, $this->digest) || ! is_string($der)) {
            throw KeyException::unreadable($key);
        }

        return $this->fromDer($der);
    }

    public function verify(string $base, string $signature, Key $key): bool
    {
        if (strlen($signature) !== $this->width * 2) {
            return false;
        }

        $material = openssl_pkey_get_public($key->publicKey ?? throw KeyException::missing($key, 'public_key'))
            ?: throw KeyException::unreadable($key);

        return openssl_verify($base, $this->toDer($signature), $material, $this->digest) === 1;
    }

    /**
     * SEQUENCE { INTEGER r, INTEGER s } to r || s.
     */
    private function fromDer(string $der): string
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7F : 0);
        $output = '';

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\x00");
            $output .= str_pad($integer, $this->width, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $output;
    }

    /**
     * r || s to SEQUENCE { INTEGER r, INTEGER s }, adding a leading zero where the high bit would
     * otherwise make an INTEGER negative.
     */
    private function toDer(string $raw): string
    {
        $sequence = '';

        foreach (str_split($raw, $this->width) as $integer) {
            $integer = ltrim($integer, "\x00");

            if ($integer === '' || ord($integer[0]) & 0x80) {
                $integer = "\x00".$integer;
            }

            $sequence .= "\x02".chr(strlen($integer)).$integer;
        }

        $length = strlen($sequence);

        return "\x30".($length < 0x80 ? chr($length) : "\x81".chr($length)).$sequence;
    }
}
