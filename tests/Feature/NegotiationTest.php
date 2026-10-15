<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

describe('406 Not Acceptable', function (): void {
    beforeEach(function (): void {
        Route::get('api/reports', fn () => ['ok' => true])->middleware('produces:application/json');
    });

    it('serves an acceptable request and says the response varies by Accept', function (?string $accept): void {
        $this->get('api/reports', array_filter(['Accept' => $accept]))
            ->assertOk()
            ->assertHeader('Vary', 'Accept');
    })->with([
        'no Accept' => [null],
        'exact' => ['application/json'],
        'wildcard subtype' => ['application/*;q=0.5'],
        'anything' => ['*/*'],
        'among others' => ['text/html, application/json;q=0.9'],
    ]);

    it('answers 406 with the apiguide.dev type and what it can produce', function (string $accept): void {
        $this->get('api/reports', ['Accept' => $accept])
            ->assertStatus(406)
            ->assertHeader('Vary', 'Accept')
            ->assertProblemType('https://apiguide.dev/errors/not-acceptable', 406)
            ->assertJsonPath('detail', 'This endpoint can only produce application/json, which does not match the Accept header.');
    })->with([
        'another type' => ['application/xml'],
        'refused outright' => ['application/json;q=0'],
        'refused under a wildcard' => ['*/*, application/json;q=0'],
    ]);
});

describe('415 Unsupported Media Type', function (): void {
    beforeEach(function (): void {
        Route::post('api/leads', fn () => ['ok' => true])->middleware('consumes:application/json');
        Route::patch('api/leads/1', fn () => ['ok' => true])->middleware('consumes:application/merge-patch+json');
    });

    it('accepts a supported type, with or without parameters', function (string $type): void {
        $this->call('POST', 'api/leads', [], [], [], ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json'], '{"a":1}')->assertOk();
    })->with(['application/json', 'application/json; charset=utf-8', 'Application/JSON']);

    it('accepts a request with no content at all', function (): void {
        $this->postJson('api/leads')->assertOk();
    });

    it('answers 415 with the apiguide.dev type and an Accept header (RFC 9110 section 15.5.16)', function (): void {
        $this->call('POST', 'api/leads', [], [], [], ['CONTENT_TYPE' => 'application/xml', 'HTTP_ACCEPT' => 'application/json'], '<a/>')
            ->assertStatus(415)
            ->assertHeader('Accept', 'application/json')
            ->assertHeaderMissing('Accept-Patch')
            ->assertProblemType('https://apiguide.dev/errors/unsupported-media-type', 415)
            ->assertJsonPath('detail', "The Content-Type 'application/xml' is not supported. Supported content types: application/json.");
    });

    it('refuses content without a Content-Type', function (): void {
        // Symfony's test requests default a POST to a form Content-Type, so it is blanked here.
        $this->call('POST', 'api/leads', [], [], [], ['CONTENT_TYPE' => '', 'HTTP_ACCEPT' => 'application/json'], '{"a":1}')
            ->assertStatus(415)
            ->assertJsonPath('detail', 'The request has content but no Content-Type. Supported content types: application/json.');
    });

    it('adds Accept-Patch to a refused PATCH (RFC 5789 section 2.2)', function (): void {
        $this->call('PATCH', 'api/leads/1', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], '{"a":1}')
            ->assertStatus(415)
            ->assertHeader('Accept-Patch', 'application/merge-patch+json');
    });
});

describe('request IDs', function (): void {
    beforeEach(function (): void {
        Route::get('api/ping', fn () => ['request_id' => Context::get('request_id')]);
    });

    it('assigns a UUIDv7, sends it back and puts it in the log context', function (): void {
        $response = $this->getJson('api/ping')->assertRequestId();
        $id = $response->headers->get('X-Request-Id');

        expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
        $response->assertJsonPath('request_id', $id);
    });

    it('keeps a well-formed ID a proxy sent', function (): void {
        $this->getJson('api/ping', ['X-Request-Id' => 'lb-7f3a91'])
            ->assertRequestId('lb-7f3a91')
            ->assertJsonPath('request_id', 'lb-7f3a91');
    });

    it('replaces one that is not', function (): void {
        $id = $this->getJson('api/ping', ['X-Request-Id' => str_repeat('a', 201)])->headers->get('X-Request-Id');

        expect($id)->not->toBe(str_repeat('a', 201))->and(strlen((string) $id))->toBe(36);
    });

    it('is on error responses too', function (): void {
        $this->getJson('api/nothing')->assertNotFound()->assertRequestId();
    });
});

describe('trace context', function (): void {
    beforeEach(function (): void {
        Route::get('api/trace', fn () => ['trace_id' => Context::get('trace_id'), 'span_id' => Context::get('span_id')]);
        Route::get('api/outgoing', function () {
            Http::get('https://upstream.example.com/');

            return ['span_id' => Context::get('span_id')];
        });
    });

    it('continues an incoming trace, with a new span for this operation', function (): void {
        $response = $this->getJson('api/trace', ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01']);

        $response->assertTraceparent('4bf92f3577b34da6a3ce929d0e0e4736')
            ->assertJsonPath('trace_id', '4bf92f3577b34da6a3ce929d0e0e4736');

        $span = $response->json('span_id');

        expect($span)->not->toBe('00f067aa0ba902b7')
            ->and($response->headers->get('traceparent'))->toBe("00-4bf92f3577b34da6a3ce929d0e0e4736-{$span}-01");
    });

    it('starts a trace for logs, but sends no traceparent to a caller that did not send one (section 6.3)', function (): void {
        $response = $this->getJson('api/trace')->assertHeaderMissing('traceparent');

        expect($response->json('trace_id'))->toMatch('/^[0-9a-f]{32}$/');
    });

    it('restarts the trace on an invalid traceparent and drops tracestate', function (): void {
        $response = $this->getJson('api/trace', [
            'traceparent' => '00-00000000000000000000000000000000-00f067aa0ba902b7-01',
            'tracestate' => 'vendor=abc',
        ]);

        $response->assertHeaderMissing('traceparent');

        expect($response->json('trace_id'))->not->toBe('00000000000000000000000000000000');
    });

    it('carries the trace onto outgoing HTTP client requests, with tracestate', function (): void {
        Http::fake();

        $span = $this->getJson('api/outgoing', [
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
            'tracestate' => 'vendor=abc',
        ])->json('span_id');

        Http::assertSent(fn (ClientRequest $request) => $request->header('traceparent') === ["00-4bf92f3577b34da6a3ce929d0e0e4736-{$span}-01"]
            && $request->header('tracestate') === ['vendor=abc']);
    });

    it('leaves a traceparent something else already set on an outgoing request', function (): void {
        Http::fake();
        Route::get('api/instrumented', function () {
            Http::withHeaders(['traceparent' => '00-11111111111111111111111111111111-2222222222222222-01'])->get('https://upstream.example.com/');

            return [];
        });

        $this->getJson('api/instrumented');

        Http::assertSent(fn (ClientRequest $request) => $request->header('traceparent') === ['00-11111111111111111111111111111111-2222222222222222-01']);
    });
});
