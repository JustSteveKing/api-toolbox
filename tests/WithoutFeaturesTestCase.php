<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

abstract class WithoutFeaturesTestCase extends TestCase
{
    protected function features(): array
    {
        return [];
    }
}
