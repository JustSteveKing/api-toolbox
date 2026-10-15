<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\ConditionalRequests\ConditionalRequestsServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Deprecation\DeprecationServiceProvider;
use JustSteveKing\Toolbox\Pagination\PaginationServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;
use JustSteveKing\Toolbox\Testing\TestingServiceProvider;

/**
 * Registers each enabled feature's own provider and nothing else.
 *
 * Fortify registers everything and gates only its routes. This goes further: a disabled feature's
 * provider is never registered, so it contributes no bindings, middleware aliases, macros or
 * exception handling hooks at all.
 */
final class ApiToolboxServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<ServiceProvider>>
     */
    private const array PROVIDERS = [
        Features::PROBLEM_DETAILS => ProblemDetailsServiceProvider::class,
        Features::DEPRECATION => DeprecationServiceProvider::class,
        Features::CONDITIONAL_REQUESTS => ConditionalRequestsServiceProvider::class,
        Features::PAGINATION => PaginationServiceProvider::class,
    ];

    public function register(): void
    {
        // The merged file carries no options; see stubs/api-toolbox.php for why.
        $this->mergeConfigFrom(__DIR__.'/../config/api-toolbox.php', Features::CONFIG_KEY);

        /** @var Repository $config */
        $config = $this->app->make('config');

        $features = ConfiguredFeatures::fromConfig($config);

        $this->app->instance(EnabledFeatures::class, $features);

        foreach ($features->all() as $feature) {
            $this->app->register(self::PROVIDERS[$feature]);
        }

        if ($this->app->runningUnitTests()) {
            $this->app->register(TestingServiceProvider::class);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs/api-toolbox.php' => $this->app->configPath(Features::CONFIG_KEY.'.php'),
            ], Features::CONFIG_KEY.'-config');
        }
    }
}
