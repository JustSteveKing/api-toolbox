<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\MessageSignatures\Algorithms\Algorithms;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use JustSteveKing\Toolbox\MessageSignatures\Key;
use JustSteveKing\Toolbox\MessageSignatures\Messages\IncomingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Messages\OutgoingResponse;
use JustSteveKing\Toolbox\MessageSignatures\SignatureBase;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * RFC 9421 Appendix B, extracted verbatim into tests/Fixtures/rfc9421: its keys, and for each test
 * case the signature base and the signature the RFC publishes.
 */

function rfcFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../../Fixtures/rfc9421/'.$name);
}

function rfcCase(string $id): array
{
    return json_decode(rfcFixture('cases.json'), true)[$id];
}

/** Appendix B.2's test-request. */
function rfcRequest(): Request
{
    $request = Request::create('http://example.com/foo?param=Value&Pet=dog', 'POST', content: '{"hello": "world"}');
    $request->headers->replace([
        'Host' => 'example.com',
        'Date' => 'Tue, 20 Apr 2021 02:07:55 GMT',
        'Content-Type' => 'application/json',
        'Content-Digest' => 'sha-512=:WZDPaVn/7XgHaAy8pmojAkGWoRx2UFChF41A2svX+TaPm+AbwAgBWnrIiYllu7BNNyealdVLvRwEmTHWXvJwew==:',
        'Content-Length' => '18',
    ]);

    return $request;
}

/** Appendix B.2's test-response. */
function rfcResponse(): OutgoingResponse
{
    $response = new Response('{"message": "good dog"}', 200, [
        'Date' => 'Tue, 20 Apr 2021 02:07:56 GMT',
        'Content-Type' => 'application/json',
        'Content-Digest' => 'sha-512=:mEWXIS7MaLRuGgxOBdODa3xqM1XdEvxoYhvlCFJ41QJgJc4GTsPp29l5oGX69wWdXymyU0rjJuahq4l5aGgfLQ==:',
        'Content-Length' => '23',
    ]);

    return new OutgoingResponse($response, rfcRequest());
}

function rfcParameters(array $case): InnerList
{
    return array_values(Parser::dictionary($case['signature_input']))[0];
}

function rfcSignature(array $case): string
{
    return array_values(Parser::dictionary($case['signature']))[0]->value->bytes;
}

function rfcMessage(string $id): Message
{
    return $id === 'B.2.4' ? rfcResponse() : new IncomingRequest(rfcRequest());
}

function rfcKey(string $id): Key
{
    return match ($id) {
        'B.2.1', 'B.2.2', 'B.2.3' => new Key('test-key-rsa-pss', 'rsa-pss-sha512', privateKey: rfcFixture('rsa-pss.key.pem'), publicKey: rfcFixture('rsa-pss.pub.pem')),
        'B.2.4' => new Key('test-key-ecc-p256', 'ecdsa-p256-sha256', privateKey: rfcFixture('ecc-p256.key.pem'), publicKey: rfcFixture('ecc-p256.pub.pem')),
        'B.2.5' => new Key('test-shared-secret', 'hmac-sha256', secret: base64_decode(trim(rfcFixture('shared-secret.txt')))),
        'B.2.6' => new Key('test-key-ed25519', 'ed25519', privateKey: rfcFixture('ed25519.key.pem'), publicKey: rfcFixture('ed25519.pub.pem')),
    };
}

it('builds every Appendix B.2 signature base byte for byte', function (string $id): void {
    $case = rfcCase($id);

    expect(SignatureBase::create(rfcMessage($id), rfcParameters($case)))->toBe($case['base']);
})->with(['B.2.1', 'B.2.2', 'B.2.3', 'B.2.4', 'B.2.5', 'B.2.6']);

it('verifies the signature the RFC publishes', function (string $id): void {
    $case = rfcCase($id);
    $key = rfcKey($id);

    expect(Algorithms::named($key->algorithm)->verify($case['base'], rfcSignature($case), $key))->toBeTrue();
})->with(['B.2.4', 'B.2.5', 'B.2.6']);

it('verifies the RFC\'s RSA-PSS signatures, whose public key is a plain RSA key', function (string $id): void {
    $case = rfcCase($id);
    $key = rfcKey($id);

    expect(Algorithms::named($key->algorithm)->verify($case['base'], rfcSignature($case), $key))->toBeTrue();
})->with(['B.2.1', 'B.2.2', 'B.2.3'])->skip(PHP_VERSION_ID < 80500, 'A plain RSA key needs PHP 8.5 for RSA-PSS.');

it('produces exactly the RFC\'s signature with the deterministic algorithms', function (string $id): void {
    $case = rfcCase($id);
    $key = rfcKey($id);

    expect(Algorithms::named($key->algorithm)->sign($case['base'], $key))->toBe(rfcSignature($case));
})->with(['B.2.5', 'B.2.6']);

it('signs and verifies with every algorithm', function (string $algorithm, array $material): void {
    $key = new Key('round-trip', $algorithm, ...$material);
    $implementation = Algorithms::named($algorithm);
    $signature = $implementation->sign("\"@method\": POST\n\"@signature-params\": (\"@method\")", $key);

    expect($implementation->verify("\"@method\": POST\n\"@signature-params\": (\"@method\")", $signature, $key))->toBeTrue()
        ->and($implementation->verify("\"@method\": GET\n\"@signature-params\": (\"@method\")", $signature, $key))->toBeFalse();
})->with(function (): array {
    $p384 = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp384r1']);
    openssl_pkey_export($p384, $p384Private);
    $pss = rfcFixture('rsa-pss.key.pem');

    return [
        'hmac-sha256' => ['hmac-sha256', ['secret' => random_bytes(64)]],
        'ed25519 PEM' => ['ed25519', ['privateKey' => rfcFixture('ed25519.key.pem'), 'publicKey' => rfcFixture('ed25519.pub.pem')]],
        'ed25519 sodium' => (function () {
            $pair = sodium_crypto_sign_keypair();

            return ['ed25519', ['privateKey' => sodium_crypto_sign_secretkey($pair), 'publicKey' => sodium_crypto_sign_publickey($pair)]];
        })(),
        'ecdsa-p256-sha256' => ['ecdsa-p256-sha256', ['privateKey' => rfcFixture('ecc-p256.key.pem'), 'publicKey' => rfcFixture('ecc-p256.pub.pem')]],
        'ecdsa-p384-sha384' => ['ecdsa-p384-sha384', ['privateKey' => $p384Private, 'publicKey' => openssl_pkey_get_details($p384)['key']]],
        'rsa-v1_5-sha256' => ['rsa-v1_5-sha256', ['privateKey' => rfcFixture('rsa.key.pem'), 'publicKey' => rfcFixture('rsa.pub.pem')]],
        'rsa-pss-sha512 with an RSA-PSS key, any PHP' => ['rsa-pss-sha512', ['privateKey' => $pss, 'publicKey' => openssl_pkey_get_details(openssl_pkey_get_private($pss))['key']]],
    ];
});

it('signs RSA-PSS with a plain RSA key on PHP 8.5, with the 64-byte salt the RFC requires', function (): void {
    $key = new Key('rsa', 'rsa-pss-sha512', privateKey: rfcFixture('rsa.key.pem'), publicKey: rfcFixture('rsa.pub.pem'));
    $signature = Algorithms::named('rsa-pss-sha512')->sign('base', $key);

    expect(Algorithms::named('rsa-pss-sha512')->verify('base', $signature, $key))->toBeTrue();
})->skip(PHP_VERSION_ID < 80500, 'Needs PHP 8.5.');

it('refuses RSA-PSS with a plain RSA key before PHP 8.5, rather than sign with PKCS#1 v1.5', function (): void {
    Algorithms::named('rsa-pss-sha512')->sign('base', new Key('rsa', 'rsa-pss-sha512', privateKey: rfcFixture('rsa.key.pem')));
})->throws(JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException::class, 'needs PHP 8.5')->skip(PHP_VERSION_ID >= 80500, 'Only applies before PHP 8.5.');

it('rejects an ECDSA signature of the wrong length outright', function (): void {
    $key = rfcKey('B.2.4');

    expect(Algorithms::named('ecdsa-p256-sha256')->verify('base', str_repeat('a', 63), $key))->toBeFalse();
});
