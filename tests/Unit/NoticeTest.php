<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Deprecation\Notice;

it('needs something to say', function (): void {
    new Notice;
})->throws(InvalidArgumentException::class);

it('allows a sunset on the same moment as the deprecation', function (): void {
    $at = Notice::date('2026-01-01T00:00:00Z');

    expect(new Notice(deprecatedAt: $at, sunsetAt: $at))->toBeInstanceOf(Notice::class);
});

it('allows a sunset with no deprecation, as RFC 8594 stands alone', function (): void {
    expect(new Notice(sunsetAt: Notice::date('2027-01-01')))->toBeInstanceOf(Notice::class);
});

it('rejects links that cannot sit inside angle brackets', function (string $link): void {
    new Notice(deprecationLink: $link);
})->with(['', 'https://example.com/a b', 'https://example.com/<x>', 'https://example.com/"'])
    ->throws(InvalidArgumentException::class);

it('rejects dates it cannot read', function (): void {
    Notice::date('the twelfth of never');
})->throws(InvalidArgumentException::class, 'is not a date');
