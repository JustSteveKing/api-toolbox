<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

use JustSteveKing\Toolbox\Features;

abstract class WithOptionsTestCase extends TestCase
{
    protected function features(): array
    {
        return [
            Features::problemDetails([
                'type_base_uri' => 'https://docs.example.com/problems/',
                'paths' => ['v1/*'],
            ]),
            Features::deprecation(['global' => false]),
        ];
    }
}
