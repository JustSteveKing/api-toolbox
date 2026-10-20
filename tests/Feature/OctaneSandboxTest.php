<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use JustSteveKing\Toolbox\Negotiation\CurrentRequestId;
use JustSteveKing\Toolbox\Negotiation\CurrentTrace;
use JustSteveKing\Toolbox\Negotiation\TraceContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * Octane serves each request from a sandbox: a clone of the booted application, made the
 * container instance, with its scoped bindings fresh. Request state lives in the sandbox, so
 * anything resolved while handling the request has to come from there, not from the application
 * that booted. These tests build that sandbox by hand, so Octane does not need to be installed.
 */

/**
 * @template T
 *
 * @param  Closure(Application): T  $callback
 * @return T
 */
function inOctaneSandbox(Closure $callback): mixed
{
    $base = Container::getInstance();
    assert($base instanceof Application);

    $sandbox = clone $base;
    $sandbox->forgetScopedInstances();

    Container::setInstance($sandbox);

    try {
        return $callback($sandbox);
    } finally {
        Container::setInstance($base);
    }
}

it('identifies a problem by the request ID of the sandbox serving the request', function (): void {
    // The booted application's own request state, which must not be what is rendered.
    app(CurrentRequestId::class)->id = '01890a5d-ac96-774b-bcce-b302099a8057';

    $response = inOctaneSandbox(function (Application $sandbox) {
        $sandbox->make(CurrentRequestId::class)->id = '0192d4f2-7c3e-7a41-9b2f-6f1d2c3b4a59';

        return $sandbox->make(ExceptionHandler::class)->render(
            Request::create('api/gone', server: ['HTTP_ACCEPT' => 'application/json']),
            new NotFoundHttpException,
        );
    });

    expect(json_decode((string) $response->getContent(), true))
        ->toHaveKey('instance', 'urn:uuid:0192d4f2-7c3e-7a41-9b2f-6f1d2c3b4a59');
});

it('carries the trace of the sandbox serving the request onto outgoing HTTP client requests', function (): void {
    Http::fake();

    // The booted application's own trace, which must not leak onto this request's calls.
    app(CurrentTrace::class)->context = new TraceContext('11111111111111111111111111111111', '2222222222222222', 1);

    inOctaneSandbox(function (Application $sandbox): void {
        $current = $sandbox->make(CurrentTrace::class);
        $current->context = new TraceContext('4bf92f3577b34da6a3ce929d0e0e4736', '00f067aa0ba902b7', 1);
        $current->state = 'vendor=abc';

        Http::get('https://upstream.example.com/');
    });

    Http::assertSent(fn (ClientRequest $request) => $request->header('traceparent') === ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01']
        && $request->header('tracestate') === ['vendor=abc']);
});
