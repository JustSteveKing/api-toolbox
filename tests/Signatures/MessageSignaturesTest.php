<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\MessageSignatures\Algorithms\Algorithms;
use JustSteveKing\Toolbox\MessageSignatures\Components;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use JustSteveKing\Toolbox\MessageSignatures\Messages\IncomingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Messages\OutgoingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Messages\OutgoingResponse;
use JustSteveKing\Toolbox\MessageSignatures\Signature;
use JustSteveKing\Toolbox\MessageSignatures\SignatureBase;
use JustSteveKing\Toolbox\MessageSignatures\Signer;
use JustSteveKing\Toolbox\MessageSignatures\VerificationPolicy;
use JustSteveKing\Toolbox\MessageSignatures\VerifiedSignature;
use JustSteveKing\Toolbox\MessageSignatures\Verifier;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

const PAYLOAD = '{"event":"order.paid","id":42}';

beforeEach(function (): void {
    Route::post('api/webhooks', fn (Request $request) => [
        'signed_by' => VerifiedSignature::of($request)?->keyId,
        'covered' => VerifiedSignature::of($request)?->components,
    ])->middleware('signature.verify');

    Route::get('api/signed', fn () => ['ok' => true])->middleware('signature.sign');
});

/**
 * Headers a client would send: the request built as the app will see it, then signed.
 */
function signedHeaders(string $keyId, array $components = ['@method', '@authority', '@path', 'content-digest', 'content-type'], string $body = PAYLOAD, array $options = []): array
{
    $request = SymfonyRequest::create('http://localhost/api/webhooks', 'POST', content: $body);
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Content-Digest', 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':');

    $fields = app(Signer::class)->sign(new IncomingRequest($request), $keyId, $components, options: $options)->fields();

    return ['Content-Type' => 'application/json', 'Content-Digest' => $request->headers->get('Content-Digest'), ...$fields];
}

function postSigned(PHPUnit\Framework\TestCase $test, array $headers, string $body = PAYLOAD): Illuminate\Testing\TestResponse
{
    $server = ['HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server[$name === 'Content-Type' ? 'CONTENT_TYPE' : 'HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return $test->call('POST', 'api/webhooks', [], [], [], $server, $body);
}

describe('verifying requests', function (): void {
    it('accepts a request signed with a known key, and says who signed it', function (string $keyId): void {
        postSigned($this, signedHeaders($keyId))
            ->assertOk()
            ->assertJsonPath('signed_by', $keyId)
            ->assertJsonPath('covered', ['"@method"', '"@authority"', '"@path"', '"content-digest"', '"content-type"']);
    })->with(['webhooks', 'partner']);

    it('refuses a request with no signature, saying what to sign with Accept-Signature', function (): void {
        postSigned($this, ['Content-Type' => 'application/json'])
            ->assertStatus(401)
            ->assertProblemType('https://apiguide.dev/errors/unauthorized', 401)
            ->assertJsonPath('detail', "The request's signature could not be verified: the request is not signed.")
            ->assertHeader('Accept-Signature', 'sig1=("@method" "@authority" "@path" "content-digest");created');
    });

    it('refuses a body changed after signing, even though the signature itself checks out', function (): void {
        postSigned($this, signedHeaders('webhooks'), '{"event":"order.paid","id":4242}')
            ->assertStatus(401)
            ->assertJsonPath('detail', "The request's signature could not be verified: Content-Digest does not match the content.");
    });

    it('refuses a changed header', function (): void {
        $headers = signedHeaders('webhooks');
        $headers['Content-Type'] = 'text/plain';

        postSigned($this, $headers)->assertStatus(401)->assertJsonPath('detail', "The request's signature could not be verified: the signature does not match.");
    });

    it('refuses a key it does not know', function (): void {
        $headers = signedHeaders('webhooks');
        $headers['Signature-Input'] = str_replace('keyid="webhooks"', 'keyid="stranger"', $headers['Signature-Input']);

        postSigned($this, $headers)->assertStatus(401)->assertJsonPath('detail', "The request's signature could not be verified: no signature uses a known key.");
    });

    it('refuses a signature that does not cover what the policy requires', function (): void {
        postSigned($this, signedHeaders('webhooks', ['@method', '@authority', 'content-digest']))
            ->assertStatus(401)
            ->assertJsonPath('detail', "The request's signature could not be verified: the signature does not cover @path.");
    });

    it('requires content-digest coverage when there is a body', function (): void {
        postSigned($this, signedHeaders('webhooks', ['@method', '@authority', '@path']))
            ->assertStatus(401)
            ->assertJsonPath('detail', "The request's signature could not be verified: the signature does not cover content-digest.");
    });

    it('refuses a signature older than the maximum age', function (): void {
        $headers = signedHeaders('webhooks');

        $this->travel(301)->seconds();

        postSigned($this, $headers)->assertStatus(401)->assertJsonPath('detail', "The request's signature could not be verified: the signature is too old.");
    });

    it('refuses a signature from the future, beyond the leeway', function (): void {
        $this->travel(31)->seconds();
        $headers = signedHeaders('webhooks');
        $this->travelBack();

        postSigned($this, $headers)->assertStatus(401)->assertJsonPath('detail', "The request's signature could not be verified: the signature was created in the future.");
    });

    it('refuses an expired signature', function (): void {
        $headers = signedHeaders('webhooks', options: ['expires' => now()->addSeconds(10)->getTimestamp()]);

        $this->travel(11)->seconds();

        postSigned($this, $headers)->assertStatus(401)->assertJsonPath('detail', "The request's signature could not be verified: the signature has expired.");
    });

    it('refuses an alg that disagrees with the key, the downgrade in RFC 9421 section 7.3.6', function (): void {
        $request = SymfonyRequest::create('http://localhost/api/webhooks', 'POST', content: PAYLOAD);
        $request->headers->set('Content-Digest', 'sha-256=:'.base64_encode(hash('sha256', PAYLOAD, true)).':');
        $parameters = new InnerList(array_map(Components::item(...), ['@method', '@authority', '@path', 'content-digest']), ['created' => now()->getTimestamp(), 'keyid' => 'partner', 'alg' => 'hmac-sha256']);
        $key = app(KeyResolver::class)->resolve('partner');
        $signature = new Signature('sig1', $parameters, Algorithms::named('ed25519')->sign(SignatureBase::create(new IncomingRequest($request), $parameters), $key));

        postSigned($this, ['Content-Digest' => $request->headers->get('Content-Digest'), ...$signature->fields()])
            ->assertStatus(401)
            ->assertJsonPath('detail', "The request's signature could not be verified: the alg parameter does not match the key.");
    });

    it('accepts a good signature beside one from an unknown key, whatever the labels', function (): void {
        $headers = signedHeaders('webhooks');
        $headers['Signature-Input'] = 'proxy_sig=("@method");created=1;keyid="someone-else", '.str_replace('sig1=', 'mine=', $headers['Signature-Input']);
        $headers['Signature'] = 'proxy_sig=:AAAA:, '.str_replace('sig1=', 'mine=', $headers['Signature']);

        postSigned($this, $headers)->assertOk()->assertJsonPath('signed_by', 'webhooks');
    });

    it('answers 400 to signature fields that do not parse', function (): void {
        postSigned($this, ['Content-Type' => 'application/json', 'Signature-Input' => 'not a dictionary', 'Signature' => 'x'])
            ->assertStatus(400);
    });
});

describe('signing responses', function (): void {
    it('signs the status, type and a digest of the body, bound to the request', function (): void {
        $response = $this->getJson('api/signed')->assertOk();

        expect($response->headers->get('Signature-Input'))->toMatch('/^sig1=\("@status" "content-type" "content-digest" "@method";req "@path";req\);created=\d+;keyid="server"$/');
        $response->assertContentDigest();

        $verifier = new Verifier(app(KeyResolver::class), new VerificationPolicy(required: ['@status', 'content-digest'], coverContent: false));
        $verified = $verifier->verify(new OutgoingResponse($response->baseResponse, $response->baseRequest), (string) $response->getContent());

        expect($verified->keyId)->toBe('server');
    });
});

describe('signing outgoing requests', function (): void {
    it('signs a webhook with withSignature(), adding Content-Digest', function (): void {
        Http::fake();

        Http::withSignature('webhooks')->post('https://hooks.example.com/orders', ['event' => 'order.paid']);

        Http::assertSent(function (ClientRequest $request): bool {
            $verifier = new Verifier(app(KeyResolver::class), new VerificationPolicy(required: ['@method', '@target-uri']));
            $verified = $verifier->verify(new OutgoingRequest($request->toPsrRequest()), $request->body());

            return $verified->keyId === 'webhooks'
                && $request->header('Signature-Input')[0] === 'sig1=("@method" "@target-uri" "content-type" "content-digest");created='.now()->getTimestamp().';keyid="webhooks"';
        });
    });

    it('covers only what it is told to', function (): void {
        Http::fake();

        Http::withSignature('partner', ['@method', '@authority'], 'partner-sig', ['tag' => 'orders'])->get('https://api.example.com/x');

        Http::assertSent(fn (ClientRequest $request): bool => str_starts_with($request->header('Signature-Input')[0], 'partner-sig=("@method" "@authority");created=')
            && str_ends_with($request->header('Signature-Input')[0], ';keyid="partner";tag="orders"'));
    });
});
