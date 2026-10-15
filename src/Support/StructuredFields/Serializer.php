<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * Serialises Structured Field values, following RFC 9651 section 4.1. The output is the strict,
 * canonical form: what RFC 9421 calls "strict serialization" for signature bases.
 *
 * @phpstan-import-type BareItem from Item
 */
final class Serializer
{
    /**
     * Section 4.1.1.
     *
     * @param  list<Item|InnerList>  $members
     */
    public static function list(array $members): string
    {
        return implode(', ', array_map(self::member(...), $members));
    }

    /**
     * Section 4.1.2. A member whose value is Boolean true is written as its key alone.
     *
     * @param  array<string, Item|InnerList>  $dictionary
     */
    public static function dictionary(array $dictionary): string
    {
        $members = [];

        foreach ($dictionary as $key => $member) {
            $members[] = self::key((string) $key).($member instanceof Item && $member->value === true
                ? self::parameters($member->parameters)
                : '='.self::member($member));
        }

        return implode(', ', $members);
    }

    public static function member(Item|InnerList $member): string
    {
        return $member instanceof InnerList ? self::innerList($member) : self::item($member);
    }

    /**
     * Section 4.1.1.1.
     */
    public static function innerList(InnerList $list): string
    {
        return '('.implode(' ', array_map(self::item(...), $list->items)).')'.self::parameters($list->parameters);
    }

    /**
     * Section 4.1.3.
     */
    public static function item(Item $item): string
    {
        return self::bareItem($item->value).self::parameters($item->parameters);
    }

    /**
     * Section 4.1.1.2.
     *
     * @param  array<string, BareItem>  $parameters
     */
    public static function parameters(array $parameters): string
    {
        $output = '';

        foreach ($parameters as $key => $value) {
            $output .= ';'.self::key((string) $key).($value === true ? '' : '='.self::bareItem($value));
        }

        return $output;
    }

    /**
     * Section 4.1.3.1.
     *
     * @param  BareItem  $value
     */
    public static function bareItem(int|float|string|bool|Token|ByteSequence|Date|DisplayString $value): string
    {
        return match (true) {
            is_int($value) => self::integer($value),
            is_float($value) => self::decimal($value),
            is_string($value) => self::string($value),
            is_bool($value) => $value ? '?1' : '?0',
            $value instanceof Token => self::token($value),
            $value instanceof ByteSequence => ':'.base64_encode($value->bytes).':',
            $value instanceof Date => '@'.self::integer($value->timestamp),
            $value instanceof DisplayString => self::displayString($value),
        };
    }

    /**
     * Section 4.1.1.3.
     */
    public static function key(string $key): string
    {
        if (preg_match('/^[a-z*][a-z0-9_\-.*]*$/', $key) !== 1) {
            throw new StructuredFieldException("[{$key}] is not a valid key.");
        }

        return $key;
    }

    /**
     * Section 4.1.4.
     */
    private static function integer(int $value): string
    {
        if ($value < -999_999_999_999_999 || $value > 999_999_999_999_999) {
            throw new StructuredFieldException('An Integer has at most 15 digits.');
        }

        return (string) $value;
    }

    /**
     * Section 4.1.5: rounded to three fractional digits, ties to even, with no trailing zeros
     * beyond the first.
     */
    private static function decimal(float $value): string
    {
        $rounded = round($value, 3, PHP_ROUND_HALF_EVEN);

        if (abs($rounded) >= 1_000_000_000_000) {
            throw new StructuredFieldException('A Decimal has at most 12 integer digits.');
        }

        $output = rtrim(number_format($rounded, 3, '.', ''), '0');

        return str_ends_with($output, '.') ? $output.'0' : $output;
    }

    /**
     * Section 4.1.6.
     */
    private static function string(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            throw new StructuredFieldException('A String is printable ASCII.');
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    /**
     * Section 4.1.7.
     */
    private static function token(Token $token): string
    {
        if (preg_match('/^[A-Za-z*][!#$%&\'*+\-.^_`|~0-9A-Za-z:\/]*$/', $token->value) !== 1) {
            throw new StructuredFieldException("[{$token->value}] is not a valid Token.");
        }

        return $token->value;
    }

    /**
     * Section 4.1.11: printable ASCII other than % and " as is, everything else as lowercase %xx.
     */
    private static function displayString(DisplayString $value): string
    {
        if (! mb_check_encoding($value->value, 'UTF-8')) {
            throw new StructuredFieldException('A Display String is UTF-8.');
        }

        $output = '';

        foreach (str_split($value->value) as $byte) {
            $ord = ord($byte);
            $output .= $byte === '%' || $byte === '"' || $ord < 0x20 || $ord > 0x7E ? sprintf('%%%02x', $ord) : $byte;
        }

        return '%"'.$output.'"';
    }
}
