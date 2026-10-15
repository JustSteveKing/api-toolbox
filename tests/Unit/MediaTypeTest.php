<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Negotiation\AcceptHeader;
use JustSteveKing\Toolbox\Negotiation\MediaType;

it('gives the qualities in RFC 9110 section 12.5.1, Table 5', function (string $type, float $quality): void {
    $accept = AcceptHeader::parse('text/*;q=0.3, text/plain;q=0.7, text/plain;format=flowed, text/plain;format=fixed;q=0.4, */*;q=0.5');

    expect($accept->quality(MediaType::parse($type)))->toBe($quality);
})->with([
    ['text/plain;format=flowed', 1.0],
    ['text/plain', 0.7],
    ['text/html', 0.3],
    ['image/jpeg', 0.5],
    ['text/plain;format=fixed', 0.4],
    ['text/html;level=3', 0.3],
]);

it('treats a missing or empty Accept as accepting anything', function (?string $field): void {
    expect(AcceptHeader::parse($field)->quality(MediaType::parse('application/json')))->toBe(1.0);
})->with([null, '', '  ']);

it('treats an unlisted type as unacceptable (section 12.4.3)', function (): void {
    expect(AcceptHeader::parse('application/xml')->quality(MediaType::parse('application/json')))->toBe(0.0);
});

it('honours q=0 as "not acceptable", even under a wildcard', function (): void {
    expect(AcceptHeader::parse('*/*, application/json;q=0')->quality(MediaType::parse('application/json')))->toBe(0.0);
});

it('discards ranges with an invalid weight (section 12.4.2)', function (string $field): void {
    expect(AcceptHeader::parse($field)->quality(MediaType::parse('application/json')))->toBe(0.0);
})->with([
    'four decimals then a valid range for something else' => ['application/json;q=0.0001, text/html'],
    'above one' => ['application/json;q=1.5, text/html'],
    'not a number' => ['application/json;q=high, text/html'],
]);

it('compares type and subtype case-insensitively (section 8.3.1)', function (): void {
    expect(AcceptHeader::parse('Application/JSON')->quality(MediaType::parse('application/json')))->toBe(1.0);
});

it('keeps commas inside quoted parameters', function (): void {
    expect(AcceptHeader::parse('text/plain;note="a, b", application/json')->quality(MediaType::parse('application/json')))->toBe(1.0);
});

it('rejects what is not a media type', function (string $value): void {
    expect(MediaType::parse($value))->toBeNull();
})->with(['json', 'application/', '/json', 'application/json;charset', 'application json']);
