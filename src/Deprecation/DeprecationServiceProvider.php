<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use JustSteveKing\Toolbox\Deprecation\Http\Middleware\AddDeprecationHeaders;
use JustSteveKing\Toolbox\Features;

final class DeprecationServiceProvider extends ServiceProvider
{
    public const string ALIAS = 'deprecation';

    public function register(): void
    {
        $this->app->singleton(DeprecationSource::class, AttributeSource::class);
    }

    public function boot(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::DEPRECATION);

        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware(self::ALIAS, AddDeprecationHeaders::class);
        });

        // Global by default: it does nothing for a route without a notice, and it means the
        // attributes work without anyone remembering to add middleware as well.
        if (($options['global'] ?? true) === true) {
            $this->callAfterResolving(KernelContract::class, static function (KernelContract $kernel): void {
                if ($kernel instanceof Kernel) {
                    $kernel->pushMiddleware(AddDeprecationHeaders::class);
                }
            });
        }
    }
}
