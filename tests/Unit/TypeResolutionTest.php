<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\ProblemDetails\BaseUriTypeResolver;

it('resolves slugs to a full path by default, never a bare relative reference (section 3.1.1)', function (): void {
    expect((new BaseUriTypeResolver)->resolve('out-of-credit'))->toBe('/problems/out-of-credit');
});

it('resolves slugs against an absolute base, with or without a trailing slash', function (string $base): void {
    expect((new BaseUriTypeResolver($base))->resolve('out-of-credit'))->toBe('https://docs.example.com/problems/out-of-credit');
})->with(['https://docs.example.com/problems', 'https://docs.example.com/problems/']);

it('passes absolute URIs and full paths through untouched', function (string $type): void {
    expect((new BaseUriTypeResolver('https://docs.example.com'))->resolve($type))->toBe($type);
})->with([
    'about:blank',
    'https://iana.org/assignments/http-problem-types#quota-exceeded',
    'tag:example@example.org,2021-09-17:OutOfLuck',
    'urn:problem:out-of-credit',
    '/types/123',
]);
