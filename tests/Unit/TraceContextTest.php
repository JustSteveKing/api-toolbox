<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Negotiation\TraceContext;

it('parses the example from section 3.2', function (): void {
    $trace = TraceContext::parse('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');

    expect($trace->traceId)->toBe('4bf92f3577b34da6a3ce929d0e0e4736')
        ->and($trace->parentId)->toBe('00f067aa0ba902b7')
        ->and($trace->sampled())->toBeTrue()
        ->and($trace->toHeaderValue())->toBe('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');
});

it('restarts the trace on anything it must not accept', function (?string $value): void {
    expect(TraceContext::parse($value))->toBeNull();
})->with([
    'absent' => [null],
    'version ff' => ['ff-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
    'uppercase hex' => ['00-4BF92F3577B34DA6A3CE929D0E0E4736-00f067aa0ba902b7-01'],
    'all-zero trace-id' => ['00-00000000000000000000000000000000-00f067aa0ba902b7-01'],
    'all-zero parent-id' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'],
    'version 00 with more after the flags' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01-extra'],
    'short trace-id' => ['00-4bf92f3577b34da6a3ce929d0e0e47-00f067aa0ba902b7-01'],
    'unparseable version' => ['zz-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
    'higher version, too short' => ['01-4bf92f3577b34da6'],
]);

it('reads a higher version as far as version 00 goes (section 3.2.4)', function (): void {
    $trace = TraceContext::parse('01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01-what-the-future-holds');

    expect($trace?->toHeaderValue())->toBe('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');
});

it('clears flags version 00 does not define (section 4.3)', function (): void {
    expect(TraceContext::parse('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-ff')?->toHeaderValue())
        ->toEndWith('-01');
});

it('continues a trace with a new parent-id for this operation', function (): void {
    $incoming = TraceContext::parse('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');
    $continued = $incoming->continue();

    expect($continued->traceId)->toBe($incoming->traceId)
        ->and($continued->parentId)->not->toBe($incoming->parentId)
        ->and($continued->parentId)->toMatch('/^[0-9a-f]{16}$/')
        ->and($continued->flags)->toBe(1);
});

it('starts unsampled traces with random ids', function (): void {
    $a = TraceContext::start();
    $b = TraceContext::start();

    expect($a->toHeaderValue())->toMatch('/^00-[0-9a-f]{32}-[0-9a-f]{16}-00$/')
        ->and($a->traceId)->not->toBe($b->traceId);
});
