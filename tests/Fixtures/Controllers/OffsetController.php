<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

final class OffsetController
{
    #[Deprecation(at: '2026-07-01T00:59:59+01:00')]
    #[Sunset(at: '2027-01-01')]
    public function __invoke(): string
    {
        return 'offset';
    }
}
