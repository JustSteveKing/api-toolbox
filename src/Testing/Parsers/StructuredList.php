<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Parsers;

use InvalidArgumentException;

/**
 * Parses an RFC 9651 List of Items, following the parsing algorithm in section 4.2.
 *
 * Inner lists are rejected rather than parsed: no field this package checks uses them. Each bare
 * item is returned as a typed value: Strings and Tokens as StructuredValue, so a test can tell
 * them apart, and Integers, Decimals and Booleans as PHP scalars.
 */
final class StructuredList
{
    private string $input;

    private int $position = 0;

    private function __construct(string $input)
    {
        // Section 4.2: discard leading and trailing spaces.
        $this->input = trim($input, ' ');
    }

    /**
     * @param  list<string>  $fieldValues  Every line of the field, combined as section 4.2 says.
     * @return list<array{value: StructuredValue|int|float|bool, params: array<string, StructuredValue|int|float|bool>}>
     */
    public static function parse(array $fieldValues): array
    {
        $parser = new self(implode(', ', $fieldValues));
        $members = [];

        // Section 4.2.1.
        while (! $parser->done()) {
            $members[] = $parser->item();
            $parser->skipOws();

            if ($parser->done()) {
                return $members;
            }

            $parser->expect(',');
            $parser->skipOws();

            if ($parser->done()) {
                throw new InvalidArgumentException('A List cannot end with a comma (RFC 9651, section 4.2.1).');
            }
        }

        return $members;
    }

    /**
     * @return array{value: StructuredValue|int|float|bool, params: array<string, StructuredValue|int|float|bool>}
     */
    private function item(): array
    {
        if ($this->peek() === '(') {
            throw new InvalidArgumentException('Inner lists are not expected here.');
        }

        return ['value' => $this->bareItem(), 'params' => $this->parameters()];
    }

    /**
     * Section 4.2.3.1.
     */
    private function bareItem(): StructuredValue|int|float|bool
    {
        $char = $this->peek();

        return match (true) {
            $char === '-' || ctype_digit($char) => $this->number(),
            $char === '"' => $this->string(),
            $char === ':' => $this->byteSequence(),
            $char === '?' => $this->boolean(),
            ctype_alpha($char) || $char === '*' => $this->token(),
            default => throw new InvalidArgumentException("Unexpected [{$char}] where a bare item should start (RFC 9651, section 4.2.3.1)."),
        };
    }

    /**
     * Section 4.2.3.2.
     *
     * @return array<string, StructuredValue|int|float|bool>
     */
    private function parameters(): array
    {
        $parameters = [];

        while ($this->peek() === ';') {
            $this->position++;

            while ($this->peek() === ' ') {
                $this->position++;
            }

            $key = $this->key();
            $value = true;

            if ($this->peek() === '=') {
                $this->position++;
                $value = $this->bareItem();
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }

    /**
     * Section 4.2.3.3.
     */
    private function key(): string
    {
        if (preg_match('/\G[a-z*][a-z0-9_\-.*]*/', $this->input, $match, 0, $this->position) !== 1) {
            throw new InvalidArgumentException('A key starts with a lowercase letter or "*" (RFC 9651, section 4.2.3.3).');
        }

        $this->position += strlen($match[0]);

        return $match[0];
    }

    /**
     * Section 4.2.4: at most 15 integer digits, or 12 and 3 for a Decimal.
     */
    private function number(): int|float
    {
        if (preg_match('/\G-?(\d{1,15})(\.\d{1,3})?/', $this->input, $match, 0, $this->position) !== 1
            || (isset($match[2]) && strlen($match[1]) > 12)) {
            throw new InvalidArgumentException('Not an Integer or Decimal (RFC 9651, section 4.2.4).');
        }

        $this->position += strlen($match[0]);

        if (ctype_digit($this->peek())) {
            throw new InvalidArgumentException('An Integer has at most 15 digits (RFC 9651, section 4.2.4).');
        }

        return isset($match[2]) ? (float) $match[0] : (int) $match[0];
    }

    /**
     * Section 4.2.5.
     */
    private function string(): StructuredValue
    {
        if (preg_match('/\G"((?:[\x20\x21\x23-\x5B\x5D-\x7E]|\\\\["\\\\])*)"/', $this->input, $match, 0, $this->position) !== 1) {
            throw new InvalidArgumentException('Not a String (RFC 9651, section 4.2.5).');
        }

        $this->position += strlen($match[0]);

        return new StructuredValue(StructuredValue::STRING, (string) preg_replace('/\\\\(["\\\\])/', '$1', $match[1]));
    }

    /**
     * Section 4.2.6.
     */
    private function token(): StructuredValue
    {
        // bareItem() only calls this on an ALPHA or "*", so there is always a match.
        if (preg_match('/\G[A-Za-z*][!#$%&\'*+\-.^_`|~0-9A-Za-z:\/]*/', $this->input, $match, 0, $this->position) !== 1) {
            throw new InvalidArgumentException('Not a Token (RFC 9651, section 4.2.6).');
        }

        $this->position += strlen($match[0]);

        return new StructuredValue(StructuredValue::TOKEN, $match[0]);
    }

    /**
     * Section 4.2.7.
     */
    private function byteSequence(): StructuredValue
    {
        if (preg_match('/\G:([A-Za-z0-9+\/=]*):/', $this->input, $match, 0, $this->position) !== 1
            || base64_decode($match[1], true) === false) {
            throw new InvalidArgumentException('Not a Byte Sequence (RFC 9651, section 4.2.7).');
        }

        $this->position += strlen($match[0]);

        return new StructuredValue(StructuredValue::BYTES, $match[1]);
    }

    /**
     * Section 4.2.8.
     */
    private function boolean(): bool
    {
        if (preg_match('/\G\?([01])/', $this->input, $match, 0, $this->position) !== 1) {
            throw new InvalidArgumentException('Not a Boolean (RFC 9651, section 4.2.8).');
        }

        $this->position += 2;

        return $match[1] === '1';
    }

    private function skipOws(): void
    {
        while (in_array($this->peek(), [' ', "\t"], true)) {
            $this->position++;
        }
    }

    private function expect(string $char): void
    {
        if ($this->peek() !== $char) {
            throw new InvalidArgumentException("Expected \"{$char}\" at [".substr($this->input, $this->position).'] (RFC 9651, section 4.2.1).');
        }

        $this->position++;
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
