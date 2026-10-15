<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\MessageSignatures\Key;
use OpenSSLAsymmetricKey;

/**
 * RFC 9421 section 3.3.1: RSASSA-PSS with SHA-512, MGF1 with SHA-512, and a 64-byte salt.
 *
 * PHP reaches PSS two ways. A key whose type is RSA-PSS (the id-RSASSA-PSS OID) gets PSS from
 * OpenSSL whatever the PHP version, with a salt the length of the digest. A plain RSA key needs
 * PSS asked for, through the padding parameter openssl_sign() and openssl_verify() gained in
 * PHP 8.5; before that, such a key is refused with an explanation rather than silently signed with
 * PKCS#1 v1.5.
 */
final class RsaPssSha512 implements SignatureAlgorithm
{
    public function name(): string
    {
        return 'rsa-pss-sha512';
    }

    public function sign(string $base, Key $key): string
    {
        $material = openssl_pkey_get_private($key->privateKey ?? throw KeyException::missing($key, 'private_key'))
            ?: throw KeyException::unreadable($key);

        if (self::isPssKey($material)) {
            $signed = openssl_sign($base, $signature, $material, OPENSSL_ALGO_SHA512);
        } elseif (PHP_VERSION_ID >= 80500) {
            // @phpstan-ignore arguments.count (the padding argument is PHP 8.5's, and this branch only runs there)
            $signed = openssl_sign($base, $signature, $material, OPENSSL_ALGO_SHA512, self::padding());
        } else {
            throw self::needsPhp85($key);
        }

        if ($signed !== true || ! is_string($signature)) {
            throw KeyException::unreadable($key);
        }

        return $signature;
    }

    public function verify(string $base, string $signature, Key $key): bool
    {
        $material = openssl_pkey_get_public($key->publicKey ?? throw KeyException::missing($key, 'public_key'))
            ?: throw KeyException::unreadable($key);

        if (self::isPssKey($material)) {
            return openssl_verify($base, $signature, $material, OPENSSL_ALGO_SHA512) === 1;
        }

        if (PHP_VERSION_ID >= 80500) {
            // @phpstan-ignore arguments.count (the padding argument is PHP 8.5's, and this branch only runs there)
            return openssl_verify($base, $signature, $material, OPENSSL_ALGO_SHA512, self::padding()) === 1;
        }

        throw self::needsPhp85($key);
    }

    /**
     * PHP has no constant for an RSA-PSS key: openssl_pkey_get_details() reports its type as -1,
     * with no "rsa" details. A plain RSA key is OPENSSL_KEYTYPE_RSA. Any other unknown type is
     * treated as RSA-PSS too, and fails at signing if it is not one.
     */
    private static function isPssKey(OpenSSLAsymmetricKey $key): bool
    {
        $details = openssl_pkey_get_details($key);

        return is_array($details) && ($details['type'] ?? null) === -1;
    }

    /**
     * Read through constant() because the constant does not exist before PHP 8.5.
     */
    private static function padding(): int
    {
        $padding = constant('OPENSSL_PKCS1_PSS_PADDING');

        return is_int($padding) ? $padding : 6;
    }

    private static function needsPhp85(Key $key): KeyException
    {
        return new KeyException("Key [{$key->id}] is a plain RSA key, and RSA-PSS with one needs PHP 8.5. Use PHP 8.5, or an RSA-PSS key (openssl genpkey -algorithm RSA-PSS).");
    }
}
