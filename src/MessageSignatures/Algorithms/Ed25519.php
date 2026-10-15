<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Algorithms;

use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\MessageSignatures\Key;

/**
 * RFC 9421 section 3.3.6: Ed25519 over the signature base with no prehash.
 *
 * Always through sodium, because PHP's OpenSSL binding only signs with Ed25519 from PHP 8.4. Keys
 * can be raw sodium keys (a 64-byte secret, a 32-byte public key) or PEM, which is unwrapped from
 * the fixed DER layout RFC 8410 defines for Ed25519.
 */
final class Ed25519 implements SignatureAlgorithm
{
    /**
     * RFC 8410: PKCS #8 OneAsymmetricKey and SubjectPublicKeyInfo for id-Ed25519, up to the key.
     */
    private const string PRIVATE_PREFIX = "\x30\x2e\x02\x01\x00\x30\x05\x06\x03\x2b\x65\x70\x04\x22\x04\x20";

    private const string PUBLIC_PREFIX = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";

    public function name(): string
    {
        return 'ed25519';
    }

    public function sign(string $base, Key $key): string
    {
        return sodium_crypto_sign_detached($base, $this->secretKey($key));
    }

    public function verify(string $base, string $signature, Key $key): bool
    {
        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $base, $this->publicKey($key));
    }

    /**
     * @return non-empty-string
     */
    private function secretKey(Key $key): string
    {
        $private = $key->privateKey ?? throw KeyException::missing($key, 'private_key');

        if (strlen($private) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            return $private;
        }

        $seed = self::unwrap($private, 'PRIVATE KEY', self::PRIVATE_PREFIX) ?? throw KeyException::unreadable($key);

        return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed));
    }

    /**
     * @return non-empty-string
     */
    private function publicKey(Key $key): string
    {
        $public = $key->publicKey ?? throw KeyException::missing($key, 'public_key');

        if (strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return $public;
        }

        return self::unwrap($public, 'PUBLIC KEY', self::PUBLIC_PREFIX) ?? throw KeyException::unreadable($key);
    }

    /**
     * The 32-byte key from a PEM block whose DER is exactly $prefix followed by it.
     *
     * @return non-empty-string|null
     */
    private static function unwrap(string $pem, string $label, string $prefix): ?string
    {
        if (preg_match('/-----BEGIN '.$label.'-----(.+?)-----END '.$label.'-----/s', $pem, $match) !== 1) {
            return null;
        }

        $der = base64_decode((string) preg_replace('/\s+/', '', $match[1]), true);

        if ($der === false || strlen($der) !== strlen($prefix) + 32 || ! str_starts_with($der, $prefix)) {
            return null;
        }

        $key = substr($der, strlen($prefix));

        return $key === '' ? null : $key;
    }
}
