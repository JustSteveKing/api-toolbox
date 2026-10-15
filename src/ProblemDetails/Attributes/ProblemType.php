<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails\Attributes;

use Attribute;

/**
 * The three things section 4 says every new problem type MUST document: a type URI, a title and
 * the status code it is used with.
 *
 * $type is either an absolute URI, used as is, or a slug resolved against the configured base URI.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ProblemType
{
    public function __construct(
        public string $type,
        public string $title,
        public int $status,
    ) {}
}
