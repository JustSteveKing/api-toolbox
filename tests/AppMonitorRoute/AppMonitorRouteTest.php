<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\AsyncOperations\Http\ShowOperation;
use JustSteveKing\Toolbox\AsyncOperations\Operations;
use JustSteveKing\Toolbox\Tests\Fixtures\Jobs\GenerateReport;

it('registers no monitor route when the application owns it', function (): void {
    expect(Route::has(Operations::ROUTE))->toBeFalse();
});

it('points every 202 at the application\'s own monitor route, which answers as the package\'s would', function (): void {
    Queue::fake();
    Route::get('v2/operations/{operation}', fn (Illuminate\Http\Request $request, string $operation, ShowOperation $monitor) => $monitor($request, $operation))
        ->name(Operations::ROUTE);
    Route::post('v2/reports', fn (Operations $operations) => $operations->dispatch(new GenerateReport));

    $monitor = $this->postJson('v2/reports')->assertOperationAccepted();

    expect($monitor)->toStartWith('http://localhost/v2/operations/');

    $this->getJson($monitor)->assertOk()->assertJsonPath('status', 'pending')->assertHeader('Retry-After');
});
