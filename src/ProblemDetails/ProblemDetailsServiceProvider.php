<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\ProblemDetails\Console\MakeApiProblemCommand;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use Throwable;

final class ProblemDetailsServiceProvider extends ServiceProvider
{
    public const array DEFAULT_PATHS = ['api/*'];

    /**
     * Bearer is the scheme Sanctum, Passport and most API token schemes use (RFC 6750).
     */
    public const string DEFAULT_CHALLENGE = 'Bearer';

    public function register(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::PROBLEM_DETAILS);

        $baseUri = $options['type_base_uri'] ?? BaseUriTypeResolver::DEFAULT_BASE;
        $paths = $options['paths'] ?? self::DEFAULT_PATHS;
        $apiGuide = ($options['apiguide'] ?? true) === true;
        $challenge = self::challenge($options['www_authenticate'] ?? self::DEFAULT_CHALLENGE);

        $this->app->singleton(TypeResolver::class, static fn (): TypeResolver => new BaseUriTypeResolver(
            is_string($baseUri) ? $baseUri : BaseUriTypeResolver::DEFAULT_BASE,
        ));

        // Bound rather than shared so app.debug is read when an exception is rendered, not once.
        $this->app->bind(ProblemFactory::class, static function (Application $app) use ($apiGuide): ProblemFactory {
            /** @var Repository $config */
            $config = $app->make('config');

            return new ProblemFactory($app->make(TypeResolver::class), (bool) $config->get('app.debug'), $apiGuide);
        });

        $this->app->bind(ProblemRenderer::class, static fn (Application $app): ProblemRenderer => new ProblemRenderer(
            $app->make(ProblemFactory::class),
            is_array($paths) ? array_values(array_filter($paths, is_string(...))) : [],
            $challenge,
        ));
    }

    /**
     * RFC 9110 section 11.6.1: challenge = auth-scheme [ 1*SP ( token68 / #auth-param ) ], and
     * auth-scheme is a token. Only the scheme is checked; what follows it is the application's to
     * get right. false or null sends no challenge, which breaks the section 15.5.2 MUST and is
     * there for applications that add the header themselves.
     */
    private static function challenge(mixed $value): ?string
    {
        if ($value === false || $value === null) {
            return null;
        }

        if (! is_string($value) || preg_match("/^[!#$%&'*+\-.^_`|~0-9A-Za-z]+( .+)?$/", $value) !== 1) {
            throw new InvalidArgumentException('www_authenticate must be a challenge, such as "Bearer" or \'Bearer realm="api"\' (RFC 9110, section 11.6.1).');
        }

        return $value;
    }

    /**
     * Render callbacks run in registration order and the first non-null response wins. The
     * application's own withExceptions() callbacks are registered before any package provider
     * boots, so an application can always override what this renders.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakeApiProblemCommand::class]);

            // `php artisan stub:publish` copies this stub too, for teams that want their own.
            $this->callAfterResolving('events', static function (Dispatcher $events): void {
                $events->listen(PublishingStubs::class, static function (PublishingStubs $event): void {
                    $event->add(__DIR__.'/../../stubs/make/'.MakeApiProblemCommand::STUB, MakeApiProblemCommand::STUB);
                });
            });
        }

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if (! $handler instanceof Handler) {
                return;
            }

            $handler->renderable(
                fn (Throwable $e, Request $request): ?JsonResponse => $this->app->make(ProblemRenderer::class)->render($e, $request),
            );
        });
    }
}
