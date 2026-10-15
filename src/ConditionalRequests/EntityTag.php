<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

use InvalidArgumentException;

/**
 * An entity tag (RFC 9110, section 8.8.3):
 *
 *     entity-tag = [ weak ] opaque-tag
 *     weak       = %s"W/"
 *     opaque-tag = DQUOTE *etagc DQUOTE
 *     etagc      = %x21 / %x23-7E / obs-text
 */
final readonly class EntityTag
{
    private const string ETAGC = '[\x21\x23-\x7E\x80-\xFF]*';

    public function __construct(
        public string $opaque,
        public bool $weak = false,
    ) {
        if (preg_match('/^'.self::ETAGC.'$/', $opaque) !== 1) {
            throw new InvalidArgumentException("[{$opaque}] contains characters an entity tag cannot (RFC 9110, section 8.8.3).");
        }
    }

    public static function strong(string $opaque): self
    {
        return new self($opaque);
    }

    public static function weak(string $opaque): self
    {
        return new self($opaque, weak: true);
    }

    /**
     * A tag that changes whenever the content does, which is what makes it strong (section 8.8.1).
     */
    public static function forContent(string $content, bool $weak = false): self
    {
        return new self(hash('xxh128', $content), $weak);
    }

    public static function parse(string $value): ?self
    {
        if (preg_match('/^(W\/)?"('.self::ETAGC.')"$/', trim($value), $match) !== 1) {
            return null;
        }

        return new self($match[2], $match[1] === 'W/');
    }

    /**
     * Parses If-Match or If-None-Match: "*" / #entity-tag (sections 13.1.1 and 13.1.2).
     *
     * Splits on the commas between tags, not inside them: a comma is a legal etagc character,
     * which is why Symfony's Request::getETags(), splitting on every comma, is not used here.
     * Members that do not parse are dropped, so a malformed field behaves as a list that matches
     * nothing: If-Match fails and If-None-Match passes, as step 3 of each evaluation describes.
     *
     * @return '*'|list<self>
     */
    public static function parseList(string $field): string|array
    {
        $field = trim($field);

        if ($field === '*') {
            return '*';
        }

        preg_match_all('/(W\/)?"('.self::ETAGC.')"/', $field, $matches, PREG_SET_ORDER);

        return array_map(
            static fn (array $match): self => new self($match[2], $match[1] === 'W/'),
            $matches,
        );
    }

    /**
     * Section 8.8.3.2: both strong, and the same opaque-tag.
     */
    public function matchesStrongly(self $other): bool
    {
        return ! $this->weak && ! $other->weak && $this->opaque === $other->opaque;
    }

    /**
     * Section 8.8.3.2: the same opaque-tag, whether either is weak or not.
     */
    public function matchesWeakly(self $other): bool
    {
        return $this->opaque === $other->opaque;
    }

    public function toString(): string
    {
        return ($this->weak ? 'W/' : '').'"'.$this->opaque.'"';
    }
}
