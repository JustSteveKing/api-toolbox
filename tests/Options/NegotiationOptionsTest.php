<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('v1/ping', function () {
        Http::get('https://upstream.example.com/');

        return [];
    });

    Http::fake();
});

it('uses the configured request ID header', function (): void {
    $this->getJson('v1/ping')
        ->assertRequestId()
        ->assertHeaderMissing('X-Request-Id');
});

it('replaces incoming request IDs when they are not trusted', function (): void {
    $id = $this->getJson('v1/ping', ['Correlation-Id' => 'from-the-client'])->headers->get('Correlation-Id');

    expect($id)->not->toBe('from-the-client');
});

it('does not carry the trace onto outgoing requests when propagation is off', function (): void {
    $this->getJson('v1/ping', ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'])
        ->assertTraceparent('4bf92f3577b34da6a3ce929d0e0e4736');

    Http::assertSent(fn (ClientRequest $request) => ! $request->hasHeader('traceparent'));
});
