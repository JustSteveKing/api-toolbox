<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation\Attributes;

use Attribute;

/**
 * When a controller, or one of its actions, is expected to stop responding (RFC 8594).
 *
 *     #[Sunset(at: '2027-01-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
 *
 * A date without an offset is read as UTC.
 *
 * With $query, it applies only to requests that carry that query parameter. A URI's query is part
 * of what identifies the resource (RFC 3986, section 3), so /contacts?organization_id=X is a
 * resource of its own, and can be deprecated while /contacts is not:
 *
 *     #[Sunset(at: '2027-01-31T23:59:59Z', query: 'organization_id')]
 *
 * Repeatable, so one controller can carry a notice for the route and another for a query form.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Sunset
{
    public function __construct(
        public string $at,
        public ?string $link = null,
        public ?string $query = null,
    ) {}
}
