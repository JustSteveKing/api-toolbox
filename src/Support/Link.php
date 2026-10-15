<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support;

use InvalidArgumentException;

/**
 * One link-value for a Link header field (RFC 8288, section 3):
 *
 *     <https://api.example.com/orders?cursor=abc>; rel="next"
 */
final readonly class Link
{
    public function __construct(
        public string $uri,
        public string $rel,
    ) {
        // Section 3: the target is a URI-Reference inside angle brackets, so it cannot contain
        // whitespace, angle brackets or a double quote.
        if ($uri === '' || preg_match('/[\s<>"]/', $uri) === 1) {
            throw new InvalidArgumentException("[{$uri}] cannot be written inside a Link header's angle brackets (RFC 8288, section 3).");
        }

        // Section 3.3: rel is one or more relation types separated by single spaces.
        if (preg_match('/^[^\s"]+( [^\s"]+)*$/', $rel) !== 1) {
            throw new InvalidArgumentException("[{$rel}] is not a valid relation type list (RFC 8288, section 3.3).");
        }
    }

    /**
     * rel is always quoted. Section 3.3 allows a bare token for a single registered type, but a
     * quoted string is valid for every value, so one form serves all.
     */
    public function toHeaderValue(): string
    {
        return "<{$this->uri}>; rel=\"{$this->rel}\"";
    }
}
