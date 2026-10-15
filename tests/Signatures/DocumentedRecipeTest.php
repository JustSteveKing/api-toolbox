<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\MessageSignatures\Messages\IncomingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Signer;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/*
 * The recipe resources/boost/skills/api-toolbox-development/references/testing.md gives agents
 * for testing a signature.verify route, run as written.
 */

beforeEach(function (): void {
    Route::post('webhooks/orders', fn (Request $request) => ['ok' => true])->middleware('signature.verify');
});

it('accepts a signed webhook', function () {
    $body = '{"event":"order.paid","id":42}';
    $digest = 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':';

    $request = SymfonyRequest::create('http://localhost/webhooks/orders', 'POST', content: $body);
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Content-Digest', $digest);

    $fields = app(Signer::class)->sign(
        new IncomingRequest($request),
        'webhooks',
        ['@method', '@authority', '@path', 'content-digest', 'content-type'],
    )->fields();

    $this->call('POST', '/webhooks/orders', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_CONTENT_DIGEST' => $digest,
        'HTTP_SIGNATURE_INPUT' => $fields['Signature-Input'],
        'HTTP_SIGNATURE' => $fields['Signature'],
    ], content: $body)->assertOk();
});

it('refuses an unsigned webhook', function () {
    $this->postJson('/webhooks/orders', ['event' => 'order.paid'])
        ->assertProblemType(ApiGuide::UNAUTHORIZED, 401);
});
