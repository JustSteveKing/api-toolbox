<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Facades\ApiToolbox;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\ProblemFactory;
use JustSteveKing\Toolbox\ProblemDetails\ProblemRenderer;
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
