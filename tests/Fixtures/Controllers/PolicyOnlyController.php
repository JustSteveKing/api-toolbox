<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;

/**
 * RFC 9745 section 3.1: a deprecation policy link, published before anything is deprecated.
 */
#[Deprecation(link: 'https://developer.example.com/deprecation')]
final class PolicyOnlyController
{
    public function __invoke(): string
    {
        return 'current';
    }
}
