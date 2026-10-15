<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware\AddValidators;
use JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware\EvaluatePreconditions;
use JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware\RequirePreconditions;

/**
 * Opt-in per route, through three middleware aliases and one group:
 *
 *     conditional              = preconditions, then validators
 *     preconditions            evaluates If-* headers (412 / 304)
 *     validators               adds ETag and Last-Modified
 *     preconditions.required   428 for unsafe requests with no precondition
 */
final class ConditionalRequestsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('preconditions', EvaluatePreconditions::class);
            $router->aliasMiddleware('validators', AddValidators::class);
            $router->aliasMiddleware('preconditions.required', RequirePreconditions::class);
            $router->middlewareGroup('conditional', [EvaluatePreconditions::class, AddValidators::class]);
        });

        // The middleware read route-bound models, so they have to run after SubstituteBindings,
        // and 428 has to be decided before 412, which has to be decided before validators are
        // added to the response. Laravel sorts route middleware by this priority list.
        $this->callAfterResolving(KernelContract::class, static function (KernelContract $kernel): void {
            if ($kernel instanceof Kernel) {
                $kernel->addToMiddlewarePriorityAfter(SubstituteBindings::class, RequirePreconditions::class);
                $kernel->addToMiddlewarePriorityAfter(RequirePreconditions::class, EvaluatePreconditions::class);
                $kernel->addToMiddlewarePriorityAfter(EvaluatePreconditions::class, AddValidators::class);
            }
        });
    }
}
