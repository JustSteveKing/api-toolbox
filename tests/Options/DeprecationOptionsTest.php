<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Deprecation\Http\Middleware\AddDeprecationHeaders;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\LegacyOrdersController;

it('is not global when global is off', function (): void {
    expect(app(Kernel::class)->hasMiddleware(AddDeprecationHeaders::class))->toBeFalse();

    Route::get('v1/orders', [LegacyOrdersController::class, 'index']);

    $this->get('v1/orders')->assertHeaderMissing('Deprecation');
});

it('works per route through the deprecation alias', function (): void {
    Route::get('v1/orders', [LegacyOrdersController::class, 'index'])->middleware('deprecation');

    $this->get('v1/orders')
        ->assertHeader('Deprecation', '@1688169599')
        ->assertHeader('Sunset', 'Sun, 30 Jun 2024 23:59:59 GMT');
});
