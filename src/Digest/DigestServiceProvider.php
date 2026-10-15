<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Digest\Http\Middleware\AddDigests;
use JustSteveKing\Toolbox\Digest\Http\Middleware\VerifyDigests;

final class DigestServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('digest.verify', VerifyDigests::class);
            $router->aliasMiddleware('digest.respond', AddDigests::class);
        });
    }
}
