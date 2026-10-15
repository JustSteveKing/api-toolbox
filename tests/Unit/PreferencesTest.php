<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Preferences\Preferences;

function prefer(string ...$fields): Preferences
{
    return Preferences::fromHeader(array_values($fields));
}

it('treats the three equivalent forms in RFC 7240 section 2 the same', function (string $field): void {
    $preferences = prefer($field);

    expect($preferences->prefers('foo'))->toBeTrue()
        ->and($preferences->value('foo'))->toBeNull()
        ->and($preferences->parameters('foo'))->toBe(['bar' => null]);
})->with(['foo; bar', 'foo; bar=""', 'foo=""; bar']);

it('reads several fields as one list (section 2)', function (): void {
    $preferences = prefer('respond-async, wait=100', 'handling=lenient');

    expect($preferences->respondAsync())->toBeTrue()
        ->and($preferences->wait())->toBe(100)
        ->and($preferences->handlingLenient())->toBeTrue();
});

it('compares names case-insensitively and values case-sensitively', function (): void {
    $preferences = prefer('Return=Minimal');

    expect($preferences->prefers('return'))->toBeTrue()
        ->and($preferences->returnMinimal())->toBeFalse()
        ->and($preferences->value('RETURN'))->toBe('Minimal');
});

it('keeps only the first occurrence of a preference', function (): void {
    expect(prefer('wait=10, wait=20')->wait())->toBe(10);
});

it('treats both values of an exclusive pair as neither (sections 4.2 and 4.4)', function (string $field): void {
    $preferences = prefer($field);

    expect($preferences->returnMinimal())->toBeFalse()
        ->and($preferences->returnRepresentation())->toBeFalse()
        ->and($preferences->handlingStrict())->toBeFalse()
        ->and($preferences->handlingLenient())->toBeFalse();
})->with([
    'return' => ['return=minimal, return=representation'],
    'handling' => ['handling=strict, handling=lenient'],
]);

it('reads quoted values and parameters', function (): void {
    $preferences = prefer('return=minimal; foo="some, parameter"');

    expect($preferences->returnMinimal())->toBeTrue()
        ->and($preferences->parameters('return'))->toBe(['foo' => 'some, parameter']);
});

it('ignores a wait that is not delta-seconds', function (string $field): void {
    expect(prefer($field)->wait())->toBeNull();
})->with(['wait=soon', 'wait=-1', 'wait=1.5', 'wait']);

it('ignores what does not parse, rather than failing (section 2)', function (): void {
    $preferences = prefer('@@@, respond-async');

    expect($preferences->respondAsync())->toBeTrue();
});
