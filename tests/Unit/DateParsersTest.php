<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Testing\Parsers\HttpDate;
use JustSteveKing\Toolbox\Testing\Parsers\StructuredDate;

it('parses an IMF-fixdate', function (): void {
    expect(HttpDate::parse('Sun, 06 Nov 1994 08:49:37 GMT')->getTimestamp())->toBe(784111777);
});

it('rejects anything a server must not send as an HTTP-date', function (string $value): void {
    HttpDate::parse($value);
})->with([
    'RFC 9745 section 4 example, UTC not GMT' => ['Sun, 30 Jun 2024 23:59:59 UTC'],
    'obsolete RFC 850 form' => ['Sunday, 06-Nov-94 08:49:37 GMT'],
    'obsolete asctime form' => ['Sun Nov  6 08:49:37 1994'],
    'wrong day name' => ['Mon, 06 Nov 1994 08:49:37 GMT'],
    'impossible date' => ['Sat, 31 Feb 2026 00:00:00 GMT'],
    'single-digit day' => ['Sun, 6 Nov 1994 08:49:37 GMT'],
    'ISO 8601' => ['1994-11-06T08:49:37Z'],
])->throws(InvalidArgumentException::class);

it('parses a Structured Field Date', function (): void {
    expect(StructuredDate::parse('@1688169599')->getTimestamp())->toBe(1688169599)
        ->and(StructuredDate::parse('@-1')->getTimestamp())->toBe(-1);
});

it('rejects anything that is not a Structured Field Date', function (string $value): void {
    StructuredDate::parse($value);
})->with([
    'no @' => ['1688169599'],
    'HTTP-date' => ['Fri, 30 Jun 2023 23:59:59 GMT'],
    'boolean, as early drafts used' => ['?1'],
    'decimal' => ['@1688169599.5'],
    'sixteen digits' => ['@1234567890123456'],
    'whitespace' => ['@ 1688169599'],
])->throws(InvalidArgumentException::class);
