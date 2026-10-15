<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

#[Deprecation(at: '2027-01-01T00:00:00Z')]
#[Sunset(at: '2026-01-01T00:00:00Z')]
final class MisconfiguredController
{
    public function __invoke(): string
    {
        return 'never';
    }
}
