<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;

it('compares exactly as the table in RFC 9110 section 8.8.3.2 says', function (string $one, string $two, bool $strong, bool $weak): void {
    $a = EntityTag::parse($one);
    $b = EntityTag::parse($two);

    expect($a->matchesStrongly($b))->toBe($strong)
        ->and($a->matchesWeakly($b))->toBe($weak);
})->with([
    ['W/"1"', 'W/"1"', false, true],
    ['W/"1"', 'W/"2"', false, false],
    ['W/"1"', '"1"', false, true],
    ['"1"', '"1"', true, true],
]);

it('parses and writes the section 8.8.3 examples', function (string $value, bool $weak): void {
    $tag = EntityTag::parse($value);

    expect($tag->weak)->toBe($weak)->and($tag->toString())->toBe($value);
})->with([
    ['"xyzzy"', false],
    ['W/"xyzzy"', true],
    ['""', false],
]);

it('rejects what is not an entity-tag', function (string $value): void {
    expect(EntityTag::parse($value))->toBeNull();
})->with(['xyzzy', 'w/"xyzzy"', '"xy"zzy"', '"xy zzy"', 'W/xyzzy']);

it('refuses to build a tag that could not be written', function (): void {
    EntityTag::strong('has "quotes"');
})->throws(InvalidArgumentException::class);

it('parses lists, keeping commas that are inside a tag', function (): void {
    $tags = EntityTag::parseList('"xyzzy", "r2d2,xxxx", W/"c3piozzzz"');

    expect(array_map(fn (EntityTag $tag) => $tag->toString(), $tags))->toBe(['"xyzzy"', '"r2d2,xxxx"', 'W/"c3piozzzz"']);
});

it('reads a lone asterisk as any current representation', function (): void {
    expect(EntityTag::parseList(' * '))->toBe('*');
});

it('drops members that do not parse, so a malformed field matches nothing', function (): void {
    expect(EntityTag::parseList('xyzzy, nonsense'))->toBe([]);
});

it('hashes content to a tag that changes when the content does', function (): void {
    expect(EntityTag::forContent('a')->toString())->not->toBe(EntityTag::forContent('b')->toString())
        ->and(EntityTag::forContent('a', weak: true)->weak)->toBeTrue();
});
