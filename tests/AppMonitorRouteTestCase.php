<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

use JustSteveKing\Toolbox\Features;

abstract class AppMonitorRouteTestCase extends TestCase
{
    protected function features(): array
    {
        return [
            Features::problemDetails(),
            Features::asyncOperations(['route' => false]),
        ];
    }
}
