<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
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
use JustSteveKing\Toolbox\Pagination\PaginationServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\ProblemFactory;
use JustSteveKing\Toolbox\ProblemDetails\ProblemRenderer;
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
]);

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
