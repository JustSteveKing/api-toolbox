<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * Parses Structured Field values, following the algorithms in RFC 9651 section 4.2 step by step.
 *
 * Each public method takes every line of a field and combines them with ", ", as section 4.2
 * describes for fields sent as several lines. Any failure throws StructuredFieldException, and
 * section 4.2 says a failure means the whole field is to be ignored.
 *
 * @phpstan-import-type BareItem from Item
 */
final class Parser
{
    private string $input;

    private int $position = 0;

    private function __construct(string $input)
    {
        // Section 4.2, step 1: the input has to be ASCII.
        if (preg_match('/[^\x00-\x7F]/', $input) === 1) {
            throw new StructuredFieldException('A Structured Field is ASCII.');
        }

        $this->input = $input;
    }

    /**
     * @param  string|list<string>  $field
     * @return list<Item|InnerList>
     */
    public static function list(string|array $field): array
    {
        return self::top($field, static fn (self $parser): array => $parser->parseList());
    }

    /**
     * @param  string|list<string>  $field
     * @return array<string, Item|InnerList>
     */
    public static function dictionary(string|array $field): array
    {
        return self::top($field, static fn (self $parser): array => $parser->parseDictionary());
    }

    /**
     * @param  string|list<string>  $field
     */
    public static function item(string|array $field): Item
    {
        return self::top($field, static fn (self $parser): Item => $parser->parseItem());
    }

    /**
     * Section 4.2, steps 2 to 7.
     *
     * @template T
     *
     * @param  string|list<string>  $field
     * @param  callable(self): T  $parse
     * @return T
     */
    private static function top(string|array $field, callable $parse): mixed
    {
        $parser = new self(is_array($field) ? implode(', ', $field) : $field);
        $parser->discard(' ');

        $output = $parse($parser);

        $parser->discard(' ');

        if (! $parser->done()) {
            throw new StructuredFieldException('Unexpected characters after the value.');
        }

        return $output;
    }

    /**
     * Section 4.2.1.
     *
     * @return list<Item|InnerList>
     */
    private function parseList(): array
    {
        $members = [];

        while (! $this->done()) {
            $members[] = $this->parseItemOrInnerList();
            $this->discard(" \t");

            if ($this->done()) {
                return $members;
            }

            $this->expect(',');
            $this->discard(" \t");

            if ($this->done()) {
                throw new StructuredFieldException('A List cannot end with a comma.');
            }
        }

        return $members;
    }

    /**
     * Section 4.2.2. A repeated key overwrites the earlier value in its original position.
     *
     * @return array<string, Item|InnerList>
     */
    private function parseDictionary(): array
    {
        $dictionary = [];

        while (! $this->done()) {
            $key = $this->parseKey();

            if ($this->peek() === '=') {
                $this->position++;
                $member = $this->parseItemOrInnerList();
            } else {
                $member = new Item(true, $this->parseParameters());
            }

            $dictionary[$key] = $member;
            $this->discard(" \t");

            if ($this->done()) {
                return $dictionary;
            }

            $this->expect(',');
            $this->discard(" \t");

            if ($this->done()) {
                throw new StructuredFieldException('A Dictionary cannot end with a comma.');
            }
        }

        return $dictionary;
    }

    private function parseItemOrInnerList(): Item|InnerList
    {
        return $this->peek() === '(' ? $this->parseInnerList() : $this->parseItem();
    }

    /**
     * Section 4.2.1.2.
     */
    private function parseInnerList(): InnerList
    {
        $this->expect('(');
        $items = [];

        while (! $this->done()) {
            $this->discard(' ');

            if ($this->peek() === ')') {
                $this->position++;

                return new InnerList($items, $this->parseParameters());
            }

            $items[] = $this->parseItem();

            if (! in_array($this->peek(), [' ', ')'], true)) {
                throw new StructuredFieldException('Inner List members are separated by spaces.');
            }
        }

        throw new StructuredFieldException('An Inner List needs a closing ")".');
    }

    /**
     * Section 4.2.3.
     */
    private function parseItem(): Item
    {
        return new Item($this->parseBareItem(), $this->parseParameters());
    }

    /**
     * Section 4.2.3.1.
     *
     * @return BareItem
     */
    private function parseBareItem(): int|float|string|bool|Token|ByteSequence|Date|DisplayString
    {
        $char = $this->peek();

        return match (true) {
            $char === '-' || ($char !== '' && ctype_digit($char)) => $this->parseNumber(),
            $char === '"' => $this->parseString(),
            $char === '*' || ($char !== '' && ctype_alpha($char)) => $this->parseToken(),
            $char === ':' => $this->parseByteSequence(),
            $char === '?' => $this->parseBoolean(),
            $char === '@' => $this->parseDate(),
            $char === '%' => $this->parseDisplayString(),
            default => throw new StructuredFieldException('Expected a bare item.'),
        };
    }

    /**
     * Section 4.2.3.2.
     *
     * @return array<string, BareItem>
     */
    private function parseParameters(): array
    {
        $parameters = [];

        while ($this->peek() === ';') {
            $this->position++;
            $this->discard(' ');

            $key = $this->parseKey();
            $value = true;

            if ($this->peek() === '=') {
                $this->position++;
                $value = $this->parseBareItem();
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }

    /**
     * Section 4.2.3.3.
     */
    private function parseKey(): string
    {
        if (preg_match('/\G[a-z*][a-z0-9_\-.*]*/', $this->input, $match, 0, $this->position) !== 1) {
            throw new StructuredFieldException('A key starts with a lowercase letter or "*".');
        }

        $this->position += strlen($match[0]);

        return $match[0];
    }

    /**
     * Section 4.2.4.
     */
    private function parseNumber(): int|float
    {
        $sign = 1;

        if ($this->peek() === '-') {
            $this->position++;
            $sign = -1;
        }

        if ($this->done() || ! ctype_digit($this->peek())) {
            throw new StructuredFieldException('A number needs a digit.');
        }

        $number = '';
        $decimal = false;

        while (! $this->done()) {
            $char = $this->peek();

            if (ctype_digit($char)) {
                $number .= $char;
            } elseif (! $decimal && $char === '.') {
                if (strlen($number) > 12) {
                    throw new StructuredFieldException('A Decimal has at most 12 integer digits.');
                }

                $number .= $char;
                $decimal = true;
            } else {
                break;
            }

            $this->position++;

            if (! $decimal && strlen($number) > 15) {
                throw new StructuredFieldException('An Integer has at most 15 digits.');
            }

            if ($decimal && strlen($number) > 16) {
                throw new StructuredFieldException('A Decimal has at most 16 characters.');
            }
        }

        if (! $decimal) {
            return $sign * (int) $number;
        }

        if (str_ends_with($number, '.') || strlen(explode('.', $number)[1]) > 3) {
            throw new StructuredFieldException('A Decimal has one to three fractional digits.');
        }

        return $sign * (float) $number;
    }

    /**
     * Section 4.2.5.
     */
    private function parseString(): string
    {
        $this->expect('"');
        $output = '';

        while (! $this->done()) {
            $char = $this->input[$this->position++];

            if ($char === '\\') {
                $next = $this->next();

                if ($next !== '"' && $next !== '\\') {
                    throw new StructuredFieldException('Only " and \\ can be escaped in a String.');
                }

                $output .= $next;
            } elseif ($char === '"') {
                return $output;
            } elseif (ord($char) < 0x20 || ord($char) > 0x7E) {
                throw new StructuredFieldException('A String is printable ASCII.');
            } else {
                $output .= $char;
            }
        }

        throw new StructuredFieldException('A String needs a closing quote.');
    }

    /**
     * Section 4.2.6.
     */
    private function parseToken(): Token
    {
        preg_match('/\G[A-Za-z*][!#$%&\'*+\-.^_`|~0-9A-Za-z:\/]*/', $this->input, $match, 0, $this->position);

        $token = $match[0] ?? '';
        $this->position += strlen($token);

        return new Token($token);
    }

    /**
     * Section 4.2.7. Padding is not required, as the section says parsers SHOULD allow.
     */
    private function parseByteSequence(): ByteSequence
    {
        $this->expect(':');
        $end = strpos($this->input, ':', $this->position);

        if ($end === false) {
            throw new StructuredFieldException('A Byte Sequence needs a closing ":".');
        }

        $encoded = substr($this->input, $this->position, $end - $this->position);
        $this->position = $end + 1;

        if (preg_match('/^[A-Za-z0-9+\/=]*$/', $encoded) !== 1) {
            throw new StructuredFieldException('A Byte Sequence is base64.');
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new StructuredFieldException('A Byte Sequence is base64.');
        }

        return new ByteSequence($decoded);
    }

    /**
     * Section 4.2.8.
     */
    private function parseBoolean(): bool
    {
        $this->expect('?');

        return match ($this->next()) {
            '1' => true,
            '0' => false,
            default => throw new StructuredFieldException('A Boolean is ?0 or ?1.'),
        };
    }

    /**
     * Section 4.2.9.
     */
    private function parseDate(): Date
    {
        $this->expect('@');
        $number = $this->parseNumber();

        if (! is_int($number)) {
            throw new StructuredFieldException('A Date is an Integer.');
        }

        return new Date($number);
    }

    /**
     * Section 4.2.10.
     */
    private function parseDisplayString(): DisplayString
    {
        $this->expect('%');
        $this->expect('"');
        $bytes = '';

        while (! $this->done()) {
            $char = $this->input[$this->position++];

            if (ord($char) < 0x20 || ord($char) > 0x7E) {
                throw new StructuredFieldException('A Display String is printable ASCII.');
            }

            if ($char === '%') {
                $hex = substr($this->input, $this->position, 2);

                if (preg_match('/^[0-9a-f]{2}$/', $hex) !== 1) {
                    throw new StructuredFieldException('A Display String escapes as % and two lowercase hex digits.');
                }

                $bytes .= chr((int) hexdec($hex));
                $this->position += 2;
            } elseif ($char === '"') {
                if (! mb_check_encoding($bytes, 'UTF-8')) {
                    throw new StructuredFieldException('A Display String is UTF-8.');
                }

                return new DisplayString($bytes);
            } else {
                $bytes .= $char;
            }
        }

        throw new StructuredFieldException('A Display String needs a closing quote.');
    }

    private function discard(string $characters): void
    {
        while (! $this->done() && str_contains($characters, $this->input[$this->position])) {
            $this->position++;
        }
    }

    private function expect(string $char): void
    {
        if ($this->peek() !== $char) {
            throw new StructuredFieldException("Expected \"{$char}\".");
        }

        $this->position++;
    }

    /**
     * Consumes and returns the next character, or "" at the end.
     */
    private function next(): string
    {
        $char = $this->peek();
        $this->position++;

        return $char;
    }

    private function peek(): string
    {
        return $this->input[$this->position] ?? '';
    }

    private function done(): bool
    {
        return $this->position >= strlen($this->input);
    }
}
