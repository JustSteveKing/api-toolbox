<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\ApiToolboxServiceProvider;

beforeEach(function (): void {
    Route::post('api/leads', fn () => ['ok' => true])->middleware('consumes:application/json');

    app('translator')->addLines([
        'problems.negotiation.unsupported_media_type' => "El Content-Type ':type' no es compatible. Tipos admitidos: :types.",
    ], 'es', 'api-toolbox');
});

it('translates the detail into the application locale', function (): void {
    app()->setLocale('es');

    $this->call('POST', 'api/leads', [], [], [], ['CONTENT_TYPE' => 'application/xml', 'HTTP_ACCEPT' => 'application/json'], '<a/>')
        ->assertProblemType('https://apiguide.dev/errors/unsupported-media-type', 415)
        ->assertJsonPath('title', 'Unsupported Media Type')
        ->assertJsonPath('detail', "El Content-Type 'application/xml' no es compatible. Tipos admitidos: application/json.");
});

it('falls back to English for a locale without the line', function (): void {
    app()->setLocale('fr');

    $this->call('POST', 'api/leads', [], [], [], ['CONTENT_TYPE' => 'application/xml', 'HTTP_ACCEPT' => 'application/json'], '<a/>')
        ->assertProblem(415)
        ->assertJsonPath('detail', "The Content-Type 'application/xml' is not supported. Supported content types: application/json.");
});

it('publishes the language files for applications to translate', function (): void {
    $paths = ServiceProvider::pathsToPublish(ApiToolboxServiceProvider::class, 'api-toolbox-lang');

    expect(array_values($paths))->toBe([lang_path('vendor/api-toolbox')]);
});
