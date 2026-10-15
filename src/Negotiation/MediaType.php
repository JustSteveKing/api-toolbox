<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation;

/**
 * A media type or media range (RFC 9110, sections 8.3.1 and 12.5.1):
 *
 *     media-type  = type "/" subtype parameters
 *     media-range = ( "*" "/" "*" / type "/" "*" / type "/" subtype ) parameters
 *
 * Type, subtype and parameter names compare case-insensitively (section 8.3.1); parameter values
 * are kept as sent.
 */
final readonly class MediaType
{
    private const string TOKEN = "[!#$%&'*+\\-.^_`|~0-9A-Za-z]+";

    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public string $type,
        public string $subtype,
        public array $parameters = [],
    ) {}

    public static function parse(string $value): ?self
    {
        $parts = self::split(trim($value), ';');
        $essence = strtolower(trim(array_shift($parts) ?? ''));

        if (preg_match('/^('.self::TOKEN.')\/('.self::TOKEN.')$/', $essence, $match) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($parts as $part) {
            if (preg_match('/^\s*('.self::TOKEN.')\s*=\s*(?:('.self::TOKEN.')|"((?:[^"\\\\]|\\\\.)*)")\s*$/', $part, $param) !== 1) {
                return null;
            }

            $parameters[strtolower($param[1])] = isset($param[3])
                ? (string) preg_replace('/\\\\(.)/', '$1', $param[3])
                : ($param[2] ?? '');
        }

        return new self($match[1], $match[2], $parameters);
    }

    /**
     * Whether this range covers $type: equal, or a wildcard over it, with every parameter this
     * range names present in $type with the same value.
     */
    public function covers(self $type): bool
    {
        if ($this->type !== '*' && $this->type !== $type->type) {
            return false;
        }

        if ($this->subtype !== '*' && $this->subtype !== $type->subtype) {
            return false;
        }

        foreach ($this->parameters as $name => $value) {
            if (($type->parameters[$name] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Section 12.5.1: "the most specific reference has precedence". Parameters are more specific
     * than none, a full type more than type/*, and type/* more than * / *.
     */
    public function specificity(): int
    {
        return match (true) {
            $this->type === '*' => 0,
            $this->subtype === '*' => 1,
            default => 2,
        } * 1000 + count($this->parameters);
    }

    public function essence(): string
    {
        return $this->type.'/'.$this->subtype;
    }

    /**
     * Splits on a delimiter outside quoted strings.
     *
     * @return list<string>
     */
    public static function split(string $value, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $quoted = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quoted && $char === '\\' && $i + 1 < $length) {
                $current .= $char.$value[++$i];

                continue;
            }

            if ($char === '"') {
                $quoted = ! $quoted;
            }

            if ($char === $delimiter && ! $quoted) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }
}
