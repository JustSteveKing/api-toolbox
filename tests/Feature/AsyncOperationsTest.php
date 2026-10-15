<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\AsyncOperations\Operations;
use JustSteveKing\Toolbox\AsyncOperations\OperationStatus;
use JustSteveKing\Toolbox\AsyncOperations\OperationStore;
use JustSteveKing\Toolbox\Tests\Fixtures\Jobs\GenerateReport;

beforeEach(function (): void {
    Route::post('api/reports', fn (Operations $operations) => $operations->dispatch(new GenerateReport(request('outcome', 'succeed'))));
});

it('answers 202 pointing at the status monitor, with Retry-After', function (): void {
    Queue::fake();

    $response = $this->postJson('api/reports');
    $monitor = $response->assertOperationAccepted();

    expect($monitor)->toStartWith('http://localhost/api/operations/')
        ->and($response->headers->get('Retry-After'))->toBe('5');

    $response->assertJsonPath('status', 'pending')->assertJsonStructure(['id', 'status', 'created_at', 'updated_at']);

    Queue::assertPushed(GenerateReport::class);
});

it('says it honoured respond-async when the client asked (RFC 7240 section 4.1)', function (): void {
    Queue::fake();

    $this->postJson('api/reports', [], ['Prefer' => 'respond-async'])->assertPreferenceApplied('respond-async');
    $this->postJson('api/reports')->assertHeaderMissing('Preference-Applied');
});

it('reports a pending operation with Retry-After', function (): void {
    Queue::fake();

    $monitor = $this->postJson('api/reports')->assertOperationAccepted();

    $this->getJson($monitor)->assertOk()->assertJsonPath('status', 'pending')->assertHeader('Retry-After', '5');
});

it('sends 303 See Other to the result once the job names one (RFC 9110 section 15.4.4)', function (): void {
    $monitor = $this->postJson('api/reports')->assertOperationAccepted();

    $this->getJson($monitor)->assertStatus(303)->assertHeader('Location', 'https://api.example.com/reports/42');
});

it('reports success without a result as 200', function (): void {
    $monitor = $this->postJson('api/reports', ['outcome' => 'succeed-without-result'])->assertOperationAccepted();

    $this->getJson($monitor)->assertOk()->assertJsonPath('status', 'succeeded')->assertHeaderMissing('Retry-After');
});

it('reports a failed job as a problem, without leaking an unexpected message', function (string $outcome, array $error): void {
    $store = app(OperationStore::class);
    $operation = $store->create(null);

    $job = new GenerateReport($outcome);
    $job->middleware = [new JustSteveKing\Toolbox\AsyncOperations\Jobs\TrackOperation($operation->id)];

    // Through the (sync) queue, which is where job middleware runs; dispatchNow() skips it.
    rescue(fn () => app(Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job), report: false);

    $this->getJson("api/operations/{$operation->id}")
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error', $error);
})->with([
    'unexpected' => ['unexpected', ['type' => 'https://apiguide.dev/errors/internal-server-error', 'title' => 'Internal Server Error', 'status' => 500]],
    'http' => ['http', ['type' => 'about:blank', 'title' => 'Operation failed', 'status' => 422, 'detail' => 'The report range is empty.']],
]);

it('goes back to pending while the job has attempts left', function (): void {
    $store = app(OperationStore::class);
    $operation = $store->create(null);

    $job = new GenerateReport('unexpected');
    $job->tries = 3;
    $job->middleware = [new JustSteveKing\Toolbox\AsyncOperations\Jobs\TrackOperation($operation->id)];

    // Through the (sync) queue, which is where job middleware runs; dispatchNow() skips it.
    rescue(fn () => app(Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job), report: false);

    expect($store->find($operation->id)?->status)->toBe(OperationStatus::Pending);
});

it('shows an operation only to whoever started it', function (): void {
    Queue::fake();

    $this->actingAs(new GenericUser(['id' => 1]));
    $monitor = $this->postJson('api/reports')->assertOperationAccepted();

    $this->getJson($monitor)->assertOk();

    $this->actingAs(new GenericUser(['id' => 2]));
    $this->getJson($monitor)->assertNotFound();
});

it('answers 404 for an operation it does not know', function (): void {
    $this->getJson('api/operations/'.Illuminate\Support\Str::uuid7())->assertNotFound();
});

it('refuses a job without the Queueable trait', function (): void {
    app(Operations::class)->dispatch(new class {});
})->throws(InvalidArgumentException::class, 'needs the Queueable trait');
