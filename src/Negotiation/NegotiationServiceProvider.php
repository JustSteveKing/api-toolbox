<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Contracts\RequestIdentifier;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\Negotiation\Http\Middleware\AssignRequestId;
use JustSteveKing\Toolbox\Negotiation\Http\Middleware\EnsureAcceptable;
use JustSteveKing\Toolbox\Negotiation\Http\Middleware\EnsureSupportedMediaType;
use JustSteveKing\Toolbox\Negotiation\Http\Middleware\PropagateTraceContext;
use Psr\Http\Message\RequestInterface;

final class NegotiationServiceProvider extends ServiceProvider
{
    public const string DEFAULT_REQUEST_ID_HEADER = 'X-Request-Id';

    public function register(): void
    {
        $options = $this->options();

        $this->app->scoped(CurrentTrace::class);
        $this->app->scoped(CurrentRequestId::class);

        if (($options['request_id_header'] ?? self::DEFAULT_REQUEST_ID_HEADER) !== false) {
            $this->app->bind(RequestIdentifier::class, CurrentRequestId::class);
        }

        $this->app->bind(AssignRequestId::class, static fn (Application $app): AssignRequestId => new AssignRequestId(
            $app->make(Context::class),
            $app->make(CurrentRequestId::class),
            is_string($options['request_id_header'] ?? null) ? $options['request_id_header'] : self::DEFAULT_REQUEST_ID_HEADER,
            ($options['trust_request_id'] ?? true) === true,
        ));
    }

    public function boot(): void
    {
        $options = $this->options();
        $requestIds = ($options['request_id_header'] ?? self::DEFAULT_REQUEST_ID_HEADER) !== false;
        $tracing = ($options['trace_context'] ?? true) === true;

        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('produces', EnsureAcceptable::class);
            $router->aliasMiddleware('consumes', EnsureSupportedMediaType::class);
        });

        // Global and first, so every response, errors included, carries them, and every log line
        // written while handling the request has them.
        $this->callAfterResolving(KernelContract::class, static function (KernelContract $kernel) use ($requestIds, $tracing): void {
            if (! $kernel instanceof Kernel) {
                return;
            }

            if ($tracing) {
                $kernel->prependMiddleware(PropagateTraceContext::class);
            }

            if ($requestIds) {
                $kernel->prependMiddleware(AssignRequestId::class);
            }
        });

        if ($tracing && ($options['propagate_trace'] ?? true) === true) {
            $app = $this->app;

            $this->callAfterResolving(HttpClient::class, static function (HttpClient $http) use ($app): void {
                $http->globalRequestMiddleware(static function (RequestInterface $request) use ($app): RequestInterface {
                    $current = $app->make(CurrentTrace::class);

                    // Something else, such as an OpenTelemetry instrumentation, may have set it.
                    if ($current->context === null || $request->hasHeader('traceparent')) {
                        return $request;
                    }

                    $request = $request->withHeader('traceparent', $current->context->toHeaderValue());

                    return $current->state === null ? $request : $request->withHeader('tracestate', $current->state);
                });
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return $this->app->make(EnabledFeatures::class)->options(Features::NEGOTIATION);
    }
}
