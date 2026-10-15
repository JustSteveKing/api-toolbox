<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

use JustSteveKing\Toolbox\Features;

/**
 * Every feature, with signing keys: an HMAC webhook secret, and RFC 9421 Appendix B's Ed25519 and
 * ECDSA keys standing in for a partner and for the server.
 */
abstract class WithSignatureKeysTestCase extends TestCase
{
    protected function features(): array
    {
        $fixtures = __DIR__.'/Fixtures/rfc9421/';

        return [
            ...array_values(array_diff(Features::all(), [Features::MESSAGE_SIGNATURES])),
            Features::messageSignatures([
                'keys' => [
                    'webhooks' => ['algorithm' => 'hmac-sha256', 'secret' => 'base64:'.base64_encode('a-webhook-secret-of-some-length')],
                    'partner' => ['algorithm' => 'ed25519', 'private_key' => $fixtures.'ed25519.key.pem', 'public_key' => $fixtures.'ed25519.pub.pem'],
                    'server' => ['algorithm' => 'ecdsa-p256-sha256', 'private_key' => $fixtures.'ecc-p256.key.pem', 'public_key' => $fixtures.'ecc-p256.pub.pem'],
                ],
                'sign' => ['key' => 'server'],
            ]),
        ];
    }
}
