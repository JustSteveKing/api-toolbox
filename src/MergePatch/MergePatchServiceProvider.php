<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MergePatch;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\MergePatch\Http\Middleware\AcceptMergePatch;

final class MergePatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound, not shared: the patch is whatever the current request carries. Resolving it reads
        // the raw content, so objects and arrays keep their JSON meaning.
        $this->app->bind(MergePatch::class, static function (Application $app): MergePatch {
            $request = $app->make('request');

            return MergePatch::fromJson($request instanceof Request ? $request->getContent() : '');
        });
    }

    public function boot(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('merge-patch', AcceptMergePatch::class);
        });
    }
}
