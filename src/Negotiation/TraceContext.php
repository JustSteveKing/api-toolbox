<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation;

/**
 * A W3C Trace Context traceparent (Level 1, a W3C Recommendation):
 *
 *     traceparent = version "-" trace-id "-" parent-id "-" trace-flags
 *     00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01
 *
 * @see https://www.w3.org/TR/trace-context/
 */
final readonly class TraceContext
{
    public const string VERSION = '00';

    /**
     * Section 3.2.2.5: version 00 defines only the sampled flag. Section 4.3: unknown flags are
     * set to 0 on anything sent onward.
     */
    private const int KNOWN_FLAGS = 0x01;

    public function __construct(
        public string $traceId,
        public string $parentId,
        public int $flags = 0,
    ) {}

    /**
     * Section 3.2.4 and 4.3. Returns null when the trace has to restart: a version that cannot
     * be parsed, version ff, a malformed or all-zero id, or a version 00 value with anything
     * after the flags.
     */
    public static function parse(?string $field): ?self
    {
        if ($field === null) {
            return null;
        }

        $field = trim($field, " \t");

        if (preg_match('/^([0-9a-f]{2})-/', $field, $version) !== 1 || $version[1] === 'ff') {
            return null;
        }

        // A higher version is parsed as far as version 00's fields, which must then be followed
        // by the end of the value or a dash. Version 00 itself must end there.
        $pattern = $version[1] === self::VERSION
            ? '/^00-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})$/'
            : '/^[0-9a-f]{2}-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})(-|$)/';

        if (preg_match($pattern, $field, $match) !== 1) {
            return null;
        }

        // Sections 3.2.2.3 and 3.2.2.4: all-zero ids are invalid.
        if ($match[1] === str_repeat('0', 32) || $match[2] === str_repeat('0', 16)) {
            return null;
        }

        return new self($match[1], $match[2], (int) hexdec($match[3]) & self::KNOWN_FLAGS);
    }

    /**
     * Section 4.2: a new trace-id, and a parent-id for the current operation. Section 8.2 asks
     * for random ids, which random_bytes() gives. The sampled flag is off: this package records
     * nothing itself.
     */
    public static function start(): self
    {
        return new self(bin2hex(random_bytes(16)), bin2hex(random_bytes(8)));
    }

    /**
     * Section 4.3: the same trace, with parent-id set to the current operation.
     */
    public function continue(): self
    {
        return new self($this->traceId, bin2hex(random_bytes(8)), $this->flags);
    }

    public function sampled(): bool
    {
        return ($this->flags & 0x01) === 0x01;
    }

    public function toHeaderValue(): string
    {
        return sprintf('%s-%s-%s-%02x', self::VERSION, $this->traceId, $this->parentId, $this->flags);
    }
}
