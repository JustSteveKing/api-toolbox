<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation\Attributes;

use Attribute;

/**
 * Marks a controller, or one of its actions, as deprecated (RFC 9745).
 *
 *     #[Deprecation(at: '2026-06-30T23:59:59Z', link: 'https://docs.example.com/deprecations/v1')]
 *
 * Named after the header field rather than "Deprecated", which would collide with PHP 8.4's
 * native #[\Deprecated]: that one raises E_USER_DEPRECATED every time the method is called.
 *
 * $at may be in the future: section 2.1 says the date says when the resource "will be or has
 * been deprecated". A date without an offset is read as UTC. With no $at, only the Link is sent,
 * which section 3.1 describes as publishing a deprecation policy before deprecating anything.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Deprecation
{
    public function __construct(
        public ?string $at = null,
        public ?string $link = null,
    ) {}
}
