<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Support\ApiGuide;
use JustSteveKing\Toolbox\Support\Link;

it('writes a link-value as RFC 8288 section 3 defines it', function (): void {
    expect((new Link('https://api.example.com/posts?cursor=abc', 'next'))->toHeaderValue())
        ->toBe('<https://api.example.com/posts?cursor=abc>; rel="next"')
        ->and((new Link('/', 'start index'))->toHeaderValue())
        ->toBe('</>; rel="start index"');
});

it('refuses links it could not write', function (string $uri, string $rel): void {
    new Link($uri, $rel);
})->with([
    'empty target' => ['', 'next'],
    'space in target' => ['https://a.example/a b', 'next'],
    'angle bracket in target' => ['https://a.example/>', 'next'],
    'empty rel' => ['https://a.example', ''],
    'double space in rel' => ['https://a.example', 'next  prev'],
    'quote in rel' => ['https://a.example', 'ne"xt'],
])->throws(InvalidArgumentException::class);

it('gives every catalogue type a title', function (): void {
    $types = array_filter(
        (new ReflectionClass(ApiGuide::class))->getConstants(),
        fn (mixed $value, string $name) => $name !== 'BASE' && is_string($value),
        ARRAY_FILTER_USE_BOTH,
    );

    expect($types)->toHaveCount(55);

    foreach ($types as $type) {
        expect($type)->toStartWith('https://apiguide.dev/errors/')
            ->and(ApiGuide::title($type))->not->toBeNull();
    }
});

it('maps a status only to a type documented for that status', function (int $status, string $type): void {
    expect(ApiGuide::forStatus($status))->toBe($type);
})->with([
    [401, ApiGuide::UNAUTHORIZED],
    [404, ApiGuide::RESOURCE_NOT_FOUND],
    [412, ApiGuide::PRECONDITION_FAILED],
    [428, ApiGuide::PRECONDITION_REQUIRED],
    [429, ApiGuide::RATE_LIMIT_EXCEEDED],
    [500, ApiGuide::INTERNAL_SERVER_ERROR],
]);

it('maps nothing where the catalogue has several candidates', function (int $status): void {
    expect(ApiGuide::forStatus($status))->toBeNull();
})->with([400, 402, 418, 422]);

it('knows the status the catalogue documents each type with', function (): void {
    expect(ApiGuide::status(ApiGuide::ACCOUNT_FROZEN))->toBe(403)
        ->and(ApiGuide::status(ApiGuide::IDEMPOTENCY_KEY_CONFLICT))->toBe(422)
        ->and(ApiGuide::status('https://example.com/not-in-the-catalogue'))->toBeNull()
        ->and(ApiGuide::types())->toHaveCount(55)->toContain(ApiGuide::GONE);
});
