<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Digest\DigestAlgorithm;
use JustSteveKing\Toolbox\Digest\DigestField;

const HELLO = "{\"hello\": \"world\"}\n";

it('matches the digests RFC 9530 gives for its example content', function (): void {
    expect(DigestField::serialize(DigestAlgorithm::Sha256, HELLO))->toBe('sha-256=:RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg=:')
        ->and(DigestField::serialize(DigestAlgorithm::Sha256, '{"hello": "world"}'))->toBe('sha-256=:X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=:')
        ->and(DigestField::serialize(DigestAlgorithm::Sha512, '{"hello": "world"}'))->toBe('sha-512=:WZDPaVn/7XgHaAy8pmojAkGWoRx2UFChF41A2svX+TaPm+AbwAgBWnrIiYllu7BNNyealdVLvRwEmTHWXvJwew==:');
});

it('parses Dictionaries, keeping each member\'s raw value', function (): void {
    expect(DigestField::parse(['sha-256=:d435Qo+nKZ+gLcUHn7GQtQ72hiBVAgqoLsZnZPiTGPk=:, sha-512=:YQ==:']))
        ->toBe(['sha-256' => ':d435Qo+nKZ+gLcUHn7GQtQ72hiBVAgqoLsZnZPiTGPk=:', 'sha-512' => ':YQ==:'])
        ->and(DigestField::parse(['sha-512=3, sha-256=10, unixsum=0']))->toBe(['sha-512' => '3', 'sha-256' => '10', 'unixsum' => '0'])
        ->and(DigestField::parse(['flag']))->toBe(['flag' => '?1'])
        ->and(DigestField::parse(['a=1;p=2']))->toBe(['a' => '1']);
});

it('refuses what is not a Dictionary', function (string $field): void {
    expect(DigestField::parse([$field]))->toBeNull();
})->with(['SHA-256=:YQ==:', 'sha-256=:YQ==:,', 'sha-256 :YQ==:', '=:YQ==:']);

it('decodes Byte Sequences, and only Byte Sequences', function (): void {
    expect(DigestField::bytes(':YQ==:'))->toBe('a')
        ->and(DigestField::bytes('"YQ=="'))->toBeNull()
        // RFC 9530 Appendix B.5's own example value has a stray "=", so it is not valid base64.
        ->and(DigestField::bytes(':RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg==:'))->toBeNull();
});

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
