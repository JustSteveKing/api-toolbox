<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

final class CurrentController
{
    public function __invoke(): string
    {
        return 'current';
    }
}
