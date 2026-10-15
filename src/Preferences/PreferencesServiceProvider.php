<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Preferences;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Contracts\ClientPreferences;
use JustSteveKing\Toolbox\Preferences\Http\Middleware\ApplyPreferences;

final class PreferencesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound, not shared: the preferences belong to whichever request is current.
        $this->app->bind(Preferences::class, static function (Application $app): Preferences {
            $request = $app->make('request');

            return Preferences::fromRequest($request instanceof Request ? $request : Request::capture());
        });

        $this->app->bind(ClientPreferences::class, Preferences::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('prefer', ApplyPreferences::class);
        });
    }
}
