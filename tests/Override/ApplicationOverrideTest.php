<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Tests\Fixtures\OutOfCredit;

it('lets render callbacks from withExceptions() win over its own', function (): void {
    Route::get('api/credit', fn () => throw new OutOfCredit);

    $this->getJson('api/credit')->assertStatus(402)->assertContent('overridden by the app');
});

it('still renders what the application does not override', function (): void {
    Route::get('api/gone', fn () => abort(410));

    $this->getJson('api/gone')->assertStatus(410)->assertHeader('Content-Type', 'application/problem+json');
});
