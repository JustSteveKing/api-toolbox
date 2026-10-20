<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency;

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\Idempotency\Http\Middleware\EnsureIdempotency;

final class IdempotencyServiceProvider extends ServiceProvider
{
    public const int DEFAULT_TTL = 86400;

    public function register(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::IDEMPOTENCY);

        $this->app->singleton(IdempotencyStore::class, static function (Application $app) use ($options): IdempotencyStore {
            $store = $options['store'] ?? null;

            return new IdempotencyStore(
                $app->make(Factory::class)->store(is_string($store) ? $store : null),
                self::integer($options['ttl'] ?? null, self::DEFAULT_TTL),
                self::integer($options['lock'] ?? null, 60),
                array_values(array_filter($app->make(Encrypter::class)->getAllKeys(), is_string(...))),
            );
        });

        $this->app->bind(EnsureIdempotency::class, static function (Application $app) use ($options): EnsureIdempotency {
            $documentation = $options['documentation'] ?? null;

            return new EnsureIdempotency(
                $app->make(IdempotencyStore::class),
                is_string($documentation) ? $documentation : null,
                self::integer($options['retry_after'] ?? null, 1),
            );
        });
    }

    public function boot(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('idempotent', EnsureIdempotency::class);
        });

        // Keys are scoped to the authenticated user, so authentication has to have run.
        $this->callAfterResolving(KernelContract::class, static function (KernelContract $kernel): void {
            if ($kernel instanceof Kernel) {
                $kernel->addToMiddlewarePriorityAfter(AuthenticatesRequests::class, EnsureIdempotency::class);
            }
        });
    }

    private static function integer(mixed $value, int $default): int
    {
        return is_int($value) && $value > 0 ? $value : $default;
    }
}
