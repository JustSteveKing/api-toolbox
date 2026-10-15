<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use JustSteveKing\Toolbox\ConditionalRequests\ConditionalRequestsServiceProvider;
use JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware\EvaluatePreconditions;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use JustSteveKing\Toolbox\Deprecation\DeprecationServiceProvider;
use JustSteveKing\Toolbox\Deprecation\Http\Middleware\AddDeprecationHeaders;
use JustSteveKing\Toolbox\Facades\ApiToolbox;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\Idempotency\IdempotencyServiceProvider;
use JustSteveKing\Toolbox\Idempotency\IdempotencyStore;
use JustSteveKing\Toolbox\Negotiation\Http\Middleware\AssignRequestId;
use JustSteveKing\Toolbox\Negotiation\Http\Middleware\PropagateTraceContext;
use JustSteveKing\Toolbox\Negotiation\NegotiationServiceProvider;
use JustSteveKing\Toolbox\Pagination\PaginationServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\ProblemFactory;
use JustSteveKing\Toolbox\ProblemDetails\ProblemRenderer;
use JustSteveKing\Toolbox\RateLimitHeaders\RateLimitHeadersServiceProvider;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\LegacyOrdersController;
use JustSteveKing\Toolbox\Tests\Fixtures\OutOfCredit;

function renderCallbacks(): array
{
    $handler = app(ExceptionHandler::class);

    return (fn (): array => $this->renderCallbacks)->call($handler);
}

it('reports every feature as disabled', function (): void {
    foreach (Features::all() as $feature) {
        expect(ApiToolbox::enabled($feature))->toBeFalse();
    }

    expect(app(EnabledFeatures::class)->all())->toBe([]);
});

it('registers no problem details provider, bindings or render hooks', function (): void {
    expect(app()->getProviders(ProblemDetailsServiceProvider::class))->toBe([])
        ->and(app()->bound(TypeResolver::class))->toBeFalse()
        ->and(app()->bound(ProblemFactory::class))->toBeFalse()
        ->and(app()->bound(ProblemRenderer::class))->toBeFalse()
        ->and(renderCallbacks())->toBe([]);
});

it('registers no make:api-problem command', function (): void {
    expect(Illuminate\Support\Facades\Artisan::all())->not->toHaveKey('make:api-problem');
});

it('leaves Laravel to render exceptions', function (): void {
    Route::get('api/credit', fn () => throw new OutOfCredit);

    $this->getJson('api/credit')
        ->assertStatus(500)
        ->assertHeader('Content-Type', 'application/json');
});

it('registers no deprecation provider, binding, alias or global middleware', function (): void {
    expect(app()->getProviders(DeprecationServiceProvider::class))->toBe([])
        ->and(app()->bound(DeprecationSource::class))->toBeFalse()
        ->and(app(Router::class)->getMiddleware())->not->toHaveKey('deprecation')
        ->and(app(Kernel::class)->hasMiddleware(AddDeprecationHeaders::class))->toBeFalse();

    Route::get('api/orders', [LegacyOrdersController::class, 'index']);

    $this->get('api/orders')->assertHeaderMissing('Deprecation');
});

it('registers no testing macros for disabled features', function (string $macro): void {
    expect(TestResponse::hasMacro($macro))->toBeFalse();
})->with([
    'assertProblem', 'assertProblemType', 'assertDeprecated', 'assertNotDeprecated',
    'assertSunset', 'assertLinkHeader', 'assertNoLinkHeader',
    'assertETag', 'assertNotModified', 'assertPreconditionFailed', 'assertPreconditionRequired',
    'assertIdempotentReplay', 'assertRateLimitHeaders', 'assertTraceparent', 'assertRequestId',
    'assertCacheControl', 'assertPreferenceApplied', 'assertPreferenceNotApplied', 'assertOperationAccepted',
    'assertContentDigest', 'assertReprDigest', 'assertAcceptsPatch',
]);

it('registers no merge patch or digest aliases or bindings', function (): void {
    expect(app()->getProviders(JustSteveKing\Toolbox\MergePatch\MergePatchServiceProvider::class))->toBe([])
        ->and(app()->getProviders(JustSteveKing\Toolbox\Digest\DigestServiceProvider::class))->toBe([])
        ->and(app()->bound(JustSteveKing\Toolbox\MergePatch\MergePatch::class))->toBeFalse()
        ->and(app(Router::class)->getMiddleware())->not->toHaveKeys(['merge-patch', 'digest.verify', 'digest.respond']);
});

it('registers no preferences alias or bindings', function (): void {
    expect(app()->getProviders(JustSteveKing\Toolbox\Preferences\PreferencesServiceProvider::class))->toBe([])
        ->and(app()->bound(JustSteveKing\Toolbox\Contracts\ClientPreferences::class))->toBeFalse()
        ->and(app(Router::class)->getMiddleware())->not->toHaveKey('prefer');
});

it('registers no operations store or status monitor route', function (): void {
    expect(app()->getProviders(JustSteveKing\Toolbox\AsyncOperations\AsyncOperationsServiceProvider::class))->toBe([])
        ->and(app()->bound(JustSteveKing\Toolbox\AsyncOperations\OperationStore::class))->toBeFalse()
        ->and(app(Router::class)->getRoutes()->getByName('api-toolbox.operations.show'))->toBeNull()
        ->and(app()->bound(JustSteveKing\Toolbox\Contracts\RequestIdentifier::class))->toBeFalse();
});

it('registers no negotiation aliases or global middleware, and adds no request ID or trace', function (): void {
    expect(app()->getProviders(NegotiationServiceProvider::class))->toBe([])
        ->and(app(Router::class)->getMiddleware())->not->toHaveKeys(['produces', 'consumes'])
        ->and(app(Kernel::class)->hasMiddleware(AssignRequestId::class))->toBeFalse()
        ->and(app(Kernel::class)->hasMiddleware(PropagateTraceContext::class))->toBeFalse();

    Route::get('api/ping', fn () => []);

    $this->getJson('api/ping', ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'])
        ->assertHeaderMissing('X-Request-Id')
        ->assertHeaderMissing('traceparent');
});

it('registers no idempotency alias, binding or priority', function (): void {
    expect(app()->getProviders(IdempotencyServiceProvider::class))->toBe([])
        ->and(app()->bound(IdempotencyStore::class))->toBeFalse()
        ->and(app(Router::class)->getMiddleware())->not->toHaveKey('idempotent');
});

it('leaves Laravel\'s throttle middleware in place', function (): void {
    expect(app()->getProviders(RateLimitHeadersServiceProvider::class))->toBe([])
        ->and(app(ThrottleRequests::class)::class)->toBe(ThrottleRequests::class);
});

it('registers no conditional request aliases, group or middleware priority', function (): void {
    $router = app(Router::class);

    expect(app()->getProviders(ConditionalRequestsServiceProvider::class))->toBe([])
        ->and($router->getMiddleware())->not->toHaveKeys(['preconditions', 'validators', 'preconditions.required'])
        ->and($router->getMiddlewareGroups())->not->toHaveKey('conditional')
        ->and(app(Kernel::class)->getMiddlewarePriority())->not->toContain(EvaluatePreconditions::class);
});

it('leaves Laravel\'s cursor resolver alone', function (): void {
    expect(app()->getProviders(PaginationServiceProvider::class))->toBe([]);

    request()->query->set('cursor', 'garbage');

    // Laravel's own behaviour: an undecodable cursor is no cursor.
    expect(CursorPaginator::resolveCurrentCursor())->toBeNull();

    request()->query->set('cursor', (new Cursor(['id' => 1]))->encode());

    expect(CursorPaginator::resolveCurrentCursor())->toBeInstanceOf(Cursor::class);
});
