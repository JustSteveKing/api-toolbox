<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\RateLimitHeaders;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\RateLimitHeaders\Http\Middleware\ThrottleRequestsWithRateLimitHeaders;

/**
 * Laravel resolves middleware through the container, so binding ThrottleRequests is enough for
 * every `throttle` route to get the headers. ThrottleRequestsWithRedis, which an application
 * opts into with throttleWithRedis(), is not covered.
 */
final class RateLimitHeadersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::RATE_LIMIT_HEADERS);

        $this->app->bind(ThrottleRequests::class, static fn (Application $app): ThrottleRequests => new ThrottleRequestsWithRateLimitHeaders(
            $app->make(RateLimiter::class),
            ($options['legacy_headers'] ?? true) === true,
        ));
    }
}
