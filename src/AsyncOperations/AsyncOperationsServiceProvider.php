<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\AsyncOperations\Http\ShowOperation;
use JustSteveKing\Toolbox\Contracts\ClientPreferences;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;

final class AsyncOperationsServiceProvider extends ServiceProvider
{
    public const string DEFAULT_PATH = 'api/operations';

    public function register(): void
    {
        $options = $this->options();
        $retryAfter = self::integer($options['retry_after'] ?? null, 5);

        $this->app->singleton(OperationStore::class, static function (Application $app) use ($options): OperationStore {
            $store = $options['store'] ?? null;

            return new OperationStore(
                $app->make(Factory::class)->store(is_string($store) ? $store : null),
                self::integer($options['ttl'] ?? null, 86400),
            );
        });

        $this->app->scoped(CurrentOperation::class);

        $this->app->bind(Operations::class, static function (Application $app) use ($retryAfter): Operations {
            $request = $app->make('request');

            return new Operations(
                $app->make(OperationStore::class),
                $app->make(Dispatcher::class),
                $app->make(UrlGenerator::class),
                $request instanceof Request ? $request : Request::capture(),
                $retryAfter,
                $app->bound(ClientPreferences::class) ? $app->make(ClientPreferences::class) : null,
            );
        });

        $this->app->bind(ShowOperation::class, static fn (Application $app): ShowOperation => new ShowOperation(
            $app->make(OperationStore::class),
            $retryAfter,
        ));
    }

    public function boot(): void
    {
        $options = $this->options();

        // An application that registers the monitor itself, to document it with the rest of its
        // API, turns this one off. Its route keeps the name Operations::ROUTE, which every 202's
        // Location is built from.
        if (($options['route'] ?? true) === false) {
            return;
        }

        $path = is_string($options['path'] ?? null) ? $options['path'] : self::DEFAULT_PATH;
        $middleware = is_array($options['middleware'] ?? null) ? array_values(array_filter($options['middleware'], is_string(...))) : ['api'];

        $this->callAfterResolving('router', static function (Router $router) use ($path, $middleware): void {
            $router->get(trim($path, '/').'/{operation}', ShowOperation::class)
                ->middleware($middleware)
                ->name(Operations::ROUTE);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return $this->app->make(EnabledFeatures::class)->options(Features::ASYNC_OPERATIONS);
    }

    private static function integer(mixed $value, int $default): int
    {
        return is_int($value) && $value > 0 ? $value : $default;
    }
}
