<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Idempotency\IdempotencyKey;

it('reads the quoted form from draft section 2.1', function (): void {
    expect(IdempotencyKey::parse('"8e03978e-40d5-43e8-bc93-6894a57f9324"')?->value)->toBe('8e03978e-40d5-43e8-bc93-6894a57f9324');
});

it('reads the bare form clients send in practice', function (): void {
    expect(IdempotencyKey::parse('8e03978e-40d5-43e8-bc93-6894a57f9324')?->value)->toBe('8e03978e-40d5-43e8-bc93-6894a57f9324');
});

it('unescapes a quoted string as RFC 9651 section 4.2.5 says', function (): void {
    expect(IdempotencyKey::parse('"a \\"quoted\\" \\\\ key"')?->value)->toBe('a "quoted" \\ key');
});

it('writes the quoted form, escaping what needs it', function (): void {
    expect(IdempotencyKey::parse('"a \\"b\\""')?->toHeaderValue())->toBe('"a \\"b\\""')
        ->and(IdempotencyKey::parse('plain')?->toHeaderValue())->toBe('"plain"');
});

it('rejects what is neither form', function (string $field): void {
    expect(IdempotencyKey::parse($field))->toBeNull();
})->with([
    'empty' => [''],
    'empty string' => ['""'],
    'bare with a space' => ['two words'],
    'unterminated' => ['"abc'],
    'unknown escape' => ['"a\\nb"'],
    'non-ASCII' => ['"clé"'],
    'too long' => [str_repeat('a', 256)],
]);

it('accepts 255 characters', function (): void {
    expect(IdempotencyKey::parse(str_repeat('a', 255)))->not->toBeNull();
});
