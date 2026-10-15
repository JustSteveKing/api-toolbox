<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ApiCatalog;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\ApiCatalog\Contracts\CatalogSource;
use JustSteveKing\Toolbox\ApiCatalog\Http\Middleware\AdvertiseApiCatalog;
use JustSteveKing\Toolbox\ApiCatalog\Http\ShowApiCatalog;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;

final class ApiCatalogServiceProvider extends ServiceProvider
{
    public const string ROUTE = 'api-toolbox.api-catalog';

    /**
     * RFC 9727 section 6.1 and RFC 8615: the well-known URI.
     */
    public const string PATH = '.well-known/api-catalog';

    public function register(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::API_CATALOG);
        $apis = is_array($options['apis'] ?? null) ? array_values(array_filter($options['apis'], is_array(...))) : [];
        $catalogs = is_array($options['catalogs'] ?? null) ? array_values(array_filter($options['catalogs'], is_string(...))) : [];

        /** @var list<array<string, mixed>> $apis */
        $this->app->singleton(CatalogSource::class, static fn (): CatalogSource => new ConfiguredCatalog($apis, $catalogs));

        $this->app->bind(ApiCatalog::class, static fn (Application $app): ApiCatalog => new ApiCatalog(
            $app->make(CatalogSource::class),
            $app->make(UrlGenerator::class),
        ));

        $this->app->bind(ShowApiCatalog::class, static fn (Application $app): ShowApiCatalog => new ShowApiCatalog(
            $app->make(ApiCatalog::class),
            is_int($options['max_age'] ?? null) ? $options['max_age'] : 3600,
        ));
    }

    public function boot(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::API_CATALOG);
        $middleware = is_array($options['middleware'] ?? null) ? array_values(array_filter($options['middleware'], is_string(...))) : [];

        $this->callAfterResolving('router', static function (Router $router) use ($middleware): void {
            $router->get(self::PATH, ShowApiCatalog::class)->middleware($middleware)->name(self::ROUTE);
            $router->aliasMiddleware('api-catalog.link', AdvertiseApiCatalog::class);
        });
    }
}
