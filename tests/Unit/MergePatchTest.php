<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\MergePatch\Exceptions\MalformedMergePatch;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

it('gives every result in RFC 7396 Appendix A', function (string $original, string $patch, string $result): void {
    $applied = MergePatch::fromJson($patch)->apply(json_decode($original, false));

    expect(json_encode($applied))->toBe(json_encode(json_decode($result, false)));
})->with([
    ['{"a":"b"}', '{"a":"c"}', '{"a":"c"}'],
    ['{"a":"b"}', '{"b":"c"}', '{"a":"b","b":"c"}'],
    ['{"a":"b"}', '{"a":null}', '{}'],
    ['{"a":"b","b":"c"}', '{"a":null}', '{"b":"c"}'],
    ['{"a":["b"]}', '{"a":"c"}', '{"a":"c"}'],
    ['{"a":"c"}', '{"a":["b"]}', '{"a":["b"]}'],
    ['{"a":{"b":"c"}}', '{"a":{"b":"d","c":null}}', '{"a":{"b":"d"}}'],
    ['{"a":[{"b":"c"}]}', '{"a":[1]}', '{"a":[1]}'],
    ['["a","b"]', '["c","d"]', '["c","d"]'],
    ['{"a":"b"}', '["c"]', '["c"]'],
    ['{"a":"foo"}', 'null', 'null'],
    ['{"a":"foo"}', '"bar"', '"bar"'],
    ['{"e":null}', '{"a":1}', '{"e":null,"a":1}'],
    ['[1,2]', '{"a":"b","c":null}', '{"a":"b"}'],
    ['{}', '{"a":{"bb":{"ccc":null}}}', '{"a":{"bb":{}}}'],
]);

it('gives the result in section 3', function (): void {
    $original = ['title' => 'Goodbye!', 'author' => ['givenName' => 'John', 'familyName' => 'Doe'], 'tags' => ['example', 'sample'], 'content' => 'This will be unchanged'];
    $patch = '{"title":"Hello!","phoneNumber":"+01-123-456-7890","author":{"familyName":null},"tags":["example"]}';

    expect(MergePatch::fromJson($patch)->applyTo($original))->toBe([
        'title' => 'Hello!',
        'author' => ['givenName' => 'John'],
        'tags' => ['example'],
        'content' => 'This will be unchanged',
        'phoneNumber' => '+01-123-456-7890',
    ]);
});

it('tells {} (no change) from [] (replace everything)', function (): void {
    expect(MergePatch::fromJson('{}')->applyTo(['a' => 1]))->toBe(['a' => 1])
        ->and(MergePatch::fromJson('[]')->applyTo(['a' => 1]))->toBe([]);
});

it('lists changes ready for a model, with removed members as null', function (): void {
    $changes = MergePatch::fromJson('{"title":"Hello!","summary":null,"author":{"familyName":null}}')
        ->changesTo(['title' => 'Goodbye!', 'summary' => 'Old', 'author' => ['givenName' => 'John', 'familyName' => 'Doe'], 'body' => 'Unchanged']);

    expect($changes)->toBe(['title' => 'Hello!', 'summary' => null, 'author' => ['givenName' => 'John']]);
});

it('refuses to list member changes for a patch that replaces everything', function (): void {
    MergePatch::fromJson('["c"]')->changesTo(['a' => 'b']);
})->throws(MalformedMergePatch::class, 'cannot be applied member by member');

it('refuses a patch that is not JSON', function (string $json): void {
    MergePatch::fromJson($json);
})->with(['', '{"a":', "{'a':1}"])->throws(MalformedMergePatch::class);
