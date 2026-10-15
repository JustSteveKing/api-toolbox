<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Support\StructuredFields\ByteSequence;
use JustSteveKing\Toolbox\Support\StructuredFields\Date;
use JustSteveKing\Toolbox\Support\StructuredFields\DisplayString;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use JustSteveKing\Toolbox\Support\StructuredFields\Serializer;
use JustSteveKing\Toolbox\Support\StructuredFields\StructuredFieldException;
use JustSteveKing\Toolbox\Support\StructuredFields\Token;

/*
 * The HTTP working group's conformance suite for RFC 9651 (tests/Fixtures/structured-field-tests,
 * see SOURCE.md there). Every case is run: parsing, the parsed value, and serialisation back to
 * the canonical form.
 */

function sfCases(string $pattern): array
{
    $cases = [];

    foreach (glob(__DIR__.'/../Fixtures/structured-field-tests/'.$pattern) as $file) {
        foreach (json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) as $case) {
            $cases[basename($file, '.json').': '.$case['name']] = [$case];
        }
    }

    return $cases;
}

function sfParse(array $raw, string $type): mixed
{
    return match ($type) {
        'list' => Parser::list($raw),
        'dictionary' => Parser::dictionary($raw),
        'item' => Parser::item($raw),
    };
}

function sfSerialize(mixed $value, string $type): string
{
    return match ($type) {
        'list' => Serializer::list($value),
        'dictionary' => Serializer::dictionary($value),
        'item' => Serializer::item($value),
    };
}

/** Our values in the suite's JSON form. */
function sfToJson(mixed $value, string $type): mixed
{
    return match ($type) {
        'list' => array_map(sfMemberToJson(...), $value),
        'dictionary' => array_map(fn ($key) => [$key, sfMemberToJson($value[$key])], array_map('strval', array_keys($value))),
        'item' => sfMemberToJson($value),
    };
}

function sfMemberToJson(Item|InnerList $member): array
{
    return $member instanceof InnerList
        ? [array_map(sfMemberToJson(...), $member->items), sfParametersToJson($member->parameters)]
        : [sfBareToJson($member->value), sfParametersToJson($member->parameters)];
}

function sfParametersToJson(array $parameters): array
{
    return array_map(fn ($key) => [(string) $key, sfBareToJson($parameters[$key])], array_keys($parameters));
}

function sfBareToJson(mixed $value): mixed
{
    return match (true) {
        $value instanceof Token => ['__type' => 'token', 'value' => $value->value],
        $value instanceof ByteSequence => ['__type' => 'binary', 'value' => sfBase32($value->bytes)],
        $value instanceof Date => ['__type' => 'date', 'value' => $value->timestamp],
        $value instanceof DisplayString => ['__type' => 'displaystring', 'value' => $value->value],
        default => $value,
    };
}

/** The suite's JSON form as our values, for the serialisation-only cases. */
function sfFromJson(mixed $value, string $type): mixed
{
    return match ($type) {
        'list' => array_map(sfMemberFromJson(...), $value),
        'dictionary' => array_combine(array_column($value, 0), array_map(fn ($pair) => sfMemberFromJson($pair[1]), $value)) ?: [],
        'item' => sfMemberFromJson($value),
    };
}

function sfMemberFromJson(array $member): Item|InnerList
{
    $parameters = array_combine(array_column($member[1], 0), array_map(fn ($pair) => sfBareFromJson($pair[1]), $member[1])) ?: [];

    return is_array($member[0]) && array_is_list($member[0]) && ! isset($member[0]['__type'])
        ? new InnerList(array_map(sfMemberFromJson(...), $member[0]), $parameters)
        : new Item(sfBareFromJson($member[0]), $parameters);
}

function sfBareFromJson(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }

    return match ($value['__type']) {
        'token' => new Token($value['value']),
        'binary' => new ByteSequence(sfBase32Decode($value['value'])),
        'date' => new Date($value['value']),
        'displaystring' => new DisplayString($value['value']),
    };
}

function sfBase32(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';

    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    $output = '';

    foreach (str_split($bits, 5) as $chunk) {
        $output .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }

    return $bytes === '' ? '' : str_pad($output, (int) ceil(strlen($output) / 8) * 8, '=');
}

function sfBase32Decode(string $encoded): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';

    foreach (str_split(rtrim($encoded, '=')) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }

    $output = '';

    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $output .= chr(bindec($byte));
        }
    }

    return $encoded === '' ? '' : $output;
}

it('parses and re-serialises', function (array $case): void {
    $type = $case['header_type'];

    try {
        $parsed = sfParse($case['raw'], $type);
    } catch (StructuredFieldException $e) {
        expect(($case['must_fail'] ?? false) || ($case['can_fail'] ?? false))->toBeTrue("Failed to parse: {$e->getMessage()}");

        return;
    }

    expect($case['must_fail'] ?? false)->toBeFalse('Parsed a value that must fail.');

    expect(sfToJson($parsed, $type))->toEqual($case['expected']);

    $canonical = $case['canonical'] ?? $case['raw'];

    expect(sfSerialize($parsed, $type))->toBe(implode(', ', $canonical));
})->with(sfCases('*.json'));

it('serialises to the canonical form, or refuses to', function (array $case): void {
    $type = $case['header_type'];

    try {
        $output = sfSerialize(sfFromJson($case['expected'], $type), $type);
    } catch (StructuredFieldException|TypeError $e) {
        expect($case['must_fail'] ?? false)->toBeTrue("Failed to serialise: {$e->getMessage()}");

        return;
    }

    expect($case['must_fail'] ?? false)->toBeFalse('Serialised a value that must fail.');
    expect($output)->toBe(implode(', ', $case['canonical']));
})->with(sfCases('serialisation-tests/*.json'));
