<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Digest\DigestField;
use JustSteveKing\Toolbox\Support\DigestAlgorithm;

const HELLO = "{\"hello\": \"world\"}\n";

it('matches the digests RFC 9530 gives for its example content', function (): void {
    expect(DigestField::serialize(DigestAlgorithm::Sha256, HELLO))->toBe('sha-256=:RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg=:')
        ->and(DigestField::serialize(DigestAlgorithm::Sha256, '{"hello": "world"}'))->toBe('sha-256=:X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=:')
        ->and(DigestField::serialize(DigestAlgorithm::Sha512, '{"hello": "world"}'))->toBe('sha-512=:WZDPaVn/7XgHaAy8pmojAkGWoRx2UFChF41A2svX+TaPm+AbwAgBWnrIiYllu7BNNyealdVLvRwEmTHWXvJwew==:');
});

it('parses Dictionaries through the shared Structured Fields parser', function (): void {
    $members = DigestField::parse(['sha-256=:d435Qo+nKZ+gLcUHn7GQtQ72hiBVAgqoLsZnZPiTGPk=:, sha-512=:YQ==:']);

    expect(array_keys($members))->toBe(['sha-256', 'sha-512'])
        ->and($members['sha-512']->value)->toEqual(new JustSteveKing\Toolbox\Support\StructuredFields\ByteSequence('a'));
});

it('refuses what is not a Dictionary', function (string $field): void {
    expect(DigestField::parse([$field]))->toBeNull();
})->with([
    'uppercase key' => ['SHA-256=:YQ==:'],
    'trailing comma' => ['sha-256=:YQ==:,'],
    'missing equals' => ['sha-256 :YQ==:'],
    'no key' => ['=:YQ==:'],
    // RFC 9530 Appendix B.5's own value: the stray "=" is not valid base64, which RFC 9651 says
    // makes the whole field fail to parse.
    'Appendix B.5 invalid base64' => ['sha-256=:RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg==:'],
]);

it('picks the most preferred supported algorithm, ignoring 0 (section 4)', function (string $field, ?DigestAlgorithm $expected): void {
    expect(DigestField::preferred([$field]))->toBe($expected);
})->with([
    ['sha-256=1', DigestAlgorithm::Sha256],
    ['sha-512=3, sha-256=10, unixsum=0', DigestAlgorithm::Sha256],
    ['sha-512=10, sha-256=10', DigestAlgorithm::Sha512],
    ['sha-256=0', null],
    ['md5=10', null],
    ['sha-256=11', null],
]);
