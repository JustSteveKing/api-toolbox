<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\AsyncOperations\Operations;
use JustSteveKing\Toolbox\Tests\Fixtures\Jobs\GenerateReport;

it('puts the status monitor at the configured path, with the configured Retry-After', function (): void {
    Queue::fake();
    Route::post('v1/reports', fn (Operations $operations) => $operations->dispatch(new GenerateReport));

    $response = $this->postJson('v1/reports');
    $monitor = $response->assertOperationAccepted();

    expect($monitor)->toStartWith('http://localhost/v1/jobs/')
        ->and($response->headers->get('Retry-After'))->toBe('30');

    $this->getJson($monitor)->assertOk()->assertHeader('Retry-After', '30');
});

it('leaves instance out when told to', function (): void {
    Route::get('v1/gone', fn () => abort(410));

    $this->getJson('v1/gone')->assertJsonMissingPath('instance');
});
