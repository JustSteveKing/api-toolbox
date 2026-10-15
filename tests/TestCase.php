<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

use JustSteveKing\Toolbox\ApiToolboxServiceProvider;
use JustSteveKing\Toolbox\Features;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * What config/api-toolbox.php would hold. Evaluated where a config file would be, so inline
     * options take the same path they do in an application.
     *
     * @return list<string>
     */
    protected function features(): array
    {
        return Features::all();
    }

    protected function getPackageProviders($app): array
    {
        return [ApiToolboxServiceProvider::class];
    }

    /**
     * Testbench registers providers before defineEnvironment() runs, which is too late for a
     * package that decides what to register from config. This is where an application's config
     * files would have been loaded.
     */
    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set(Features::CONFIG_KEY.'.features', $this->features());
    }
}
