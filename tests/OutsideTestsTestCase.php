<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

/**
 * Every feature enabled, but the application believes it is in production.
 */
abstract class OutsideTestsTestCase extends TestCase
{
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['env'] = 'production';
    }
}
