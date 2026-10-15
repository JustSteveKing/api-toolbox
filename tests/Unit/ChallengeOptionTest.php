<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;

function challenge(mixed $value): ?string
{
    return (fn () => self::challenge($value))->call(new ProblemDetailsServiceProvider(app()));
}

it('accepts challenges in RFC 9110 section 11.6.1 form', function (string $value): void {
    expect(challenge($value))->toBe($value);
})->with(['Bearer', 'Bearer realm="api"', 'Basic realm="simple", Newauth realm="apps"', 'Newauth abc123==']);

it('turns the challenge off with false or null', function (mixed $value): void {
    expect(challenge($value))->toBeNull();
})->with([false, null]);

it('rejects what does not start with an auth-scheme', function (mixed $value): void {
    challenge($value);
})->with(['', ' Bearer', 'Bearer ', '"Bearer"', 'Bear(er)', true, 1])->throws(InvalidArgumentException::class);
