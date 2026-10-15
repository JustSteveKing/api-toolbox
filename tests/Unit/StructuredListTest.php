<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\RateLimitHeaders\PolicyHeaders;
use JustSteveKing\Toolbox\Testing\Parsers\StructuredList;
use JustSteveKing\Toolbox\Testing\Parsers\StructuredValue;

it('parses every RateLimit-Policy example in draft section 3.2', function (string $field, int $count): void {
    expect(StructuredList::parse([$field]))->toHaveCount($count);
})->with([
    ['"burst";q=100;w=60,"daily";q=1000;w=86400', 2],
    ['"default";q=100;w=10', 1],
    ['"permin";q=50;w=60,"perhr";q=1000;w=3600', 2],
    ['"peruser";q=100;w=60;pk=:cHsdsRa894==:', 1],
    ['"peruser";q=65535;qu="content-bytes";w=10;pk=:sdfjLJUOUH==:', 1],
]);

it('keeps the type of each value', function (): void {
    [$item] = StructuredList::parse(['"api";q=100;w=60;qu="requests";flag;tok=abc;pk=:YWJj:;d=1.5']);

    expect($item['value'])->toEqual(new StructuredValue(StructuredValue::STRING, 'api'))
        ->and($item['params']['q'])->toBe(100)
        ->and($item['params']['qu'])->toEqual(new StructuredValue(StructuredValue::STRING, 'requests'))
        ->and($item['params']['flag'])->toBeTrue()
        ->and($item['params']['tok'])->toEqual(new StructuredValue(StructuredValue::TOKEN, 'abc'))
        ->and($item['params']['pk'])->toEqual(new StructuredValue(StructuredValue::BYTES, 'YWJj'))
        ->and($item['params']['d'])->toBe(1.5);
});

it('joins lines split over several fields', function (): void {
    expect(StructuredList::parse(['"a";r=1', '"b";r=2']))->toHaveCount(2);
});

it('rejects what is not a List', function (string $field): void {
    StructuredList::parse([$field]);
})->with([
    'trailing comma' => ['"a";q=1,'],
    'uppercase key' => ['"a";Q=1'],
    'unterminated string' => ['"a;q=1'],
    'sixteen digits' => ['"a";q=1234567890123456'],
    'missing comma' => ['"a";q=1 "b";q=2'],
    'inner list' => ['("a" "b")'],
    'bad byte sequence' => ['"a";pk=:not base64!:'],
])->throws(InvalidArgumentException::class);

it('serialises the draft examples exactly', function (): void {
    $headers = new PolicyHeaders;
    $headers->add('burst', quota: 100, window: 60, remaining: 50, resetsIn: 30);
    $headers->add('daily', quota: 1000, window: 86400, remaining: 999);

    expect($headers->toArray())->toBe([
        'RateLimit-Policy' => '"burst";q=100;w=60, "daily";q=1000;w=86400',
        'RateLimit' => '"burst";r=50;t=30, "daily";r=999',
    ]);
});

it('escapes policy names as RFC 9651 Strings', function (): void {
    $headers = new PolicyHeaders;
    $headers->add("we\"ird\\name\n", quota: 1, window: 1, remaining: 0);

    expect($headers->toArray()['RateLimit-Policy'])->toBe('"we\\"ird\\\\name";q=1;w=1')
        ->and(StructuredList::parse([$headers->toArray()['RateLimit-Policy']])[0]['value']->value)->toBe('we"ird\\name');
});

it('sends nothing without a policy', function (): void {
    expect((new PolicyHeaders)->toArray())->toBe([]);
});
