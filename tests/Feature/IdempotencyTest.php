<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Idempotency\IdempotencyKey;
use JustSteveKing\Toolbox\Idempotency\IdempotencyStore;

beforeEach(function (): void {
    $this->runs = 0;
    $runs = &$this->runs;

    Route::middleware('idempotent')->group(function () use (&$runs): void {
        Route::post('api/charges', function (Request $request) use (&$runs) {
            $runs++;

            return response()->json(['id' => "ch_{$runs}", 'amount' => $request->integer('amount')], 201, ['Location' => "/api/charges/ch_{$runs}"]);
        });
        Route::post('api/refunds', function () use (&$runs) {
            $runs++;

            return response()->json(['id' => "re_{$runs}"], 201);
        });
        Route::patch('api/charges/1', function () use (&$runs) {
            $runs++;

            throw Illuminate\Validation\ValidationException::withMessages(['amount' => 'The amount must be positive.']);
        });
        Route::post('api/flaky', function () use (&$runs) {
            $runs++;

            abort_if($runs === 1, 503);

            return ['ok' => true];
        });
        Route::get('api/charges', function () use (&$runs) {
            $runs++;

            return ['run' => $runs];
        });
    });

    Route::post('api/strict', fn () => 'created')->middleware('idempotent:required');
});

function charge(PHPUnit\Framework\TestCase $test, string $key, int $amount = 50): Illuminate\Testing\TestResponse
{
    return $test->postJson('api/charges', ['amount' => $amount], ['Idempotency-Key' => $key]);
}

it('replays the stored response to a retry, without running the action again', function (): void {
    $first = charge($this, '"8e03978e-40d5-43e8-bc93-6894a57f9324"')->assertCreated();
    $retry = charge($this, '"8e03978e-40d5-43e8-bc93-6894a57f9324"');

    $retry->assertIdempotentReplay($first)->assertHeader('Location', '/api/charges/ch_1');

    expect($this->runs)->toBe(1);
});

it('treats the quoted and bare forms of a key as the same key', function (): void {
    $first = charge($this, '"abc-123"');

    charge($this, 'abc-123')->assertIdempotentReplay($first);

    expect($this->runs)->toBe(1);
});

it('runs requests with different keys separately', function (): void {
    charge($this, '"one"');
    charge($this, '"two"');

    expect($this->runs)->toBe(2);
});

it('answers a key reused with a different payload with 422 and the apiguide.dev type (draft section 2.7)', function (): void {
    charge($this, '"abc"', amount: 50);

    charge($this, '"abc"', amount: 5000)
        ->assertStatus(422)
        ->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 422);

    expect($this->runs)->toBe(1);
});

it('treats the same key on another endpoint as a different request', function (): void {
    charge($this, '"abc"');

    $this->postJson('api/refunds', ['amount' => 50], ['Idempotency-Key' => '"abc"'])->assertStatus(422);
});

it('answers a retry while the original is in flight with 409, Retry-After and the apiguide.dev type', function (): void {
    // Hold the lock the original request would hold, scoped to the client address.
    $lock = app(IdempotencyStore::class)->lock('ip:127.0.0.1', IdempotencyKey::parse('"abc"'));
    $lock->get();

    charge($this, '"abc"')
        ->assertStatus(409)
        ->assertHeader('Retry-After', '1')
        ->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 409);

    expect($this->runs)->toBe(0);

    $lock->release();

    charge($this, '"abc"')->assertCreated();
});

it('stores and replays errors too (draft section 2.6)', function (): void {
    $first = $this->patchJson('api/charges/1', [], ['Idempotency-Key' => '"abc"'])->assertStatus(422);

    $this->patchJson('api/charges/1', [], ['Idempotency-Key' => '"abc"'])->assertIdempotentReplay($first);

    expect($this->runs)->toBe(1);
});

it('does not store a server error, so the retry can succeed', function (): void {
    $this->postJson('api/flaky', [], ['Idempotency-Key' => '"abc"'])->assertStatus(503);
    $this->postJson('api/flaky', [], ['Idempotency-Key' => '"abc"'])->assertOk();

    expect($this->runs)->toBe(2);
});

it('scopes keys to the authenticated user, so two users cannot collide', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));
    charge($this, '"abc"')->assertJsonPath('id', 'ch_1');

    $this->actingAs(new GenericUser(['id' => 2]));
    charge($this, '"abc"')->assertJsonPath('id', 'ch_2');

    expect($this->runs)->toBe(2);
});

it('forgets a key after its TTL', function (): void {
    charge($this, '"abc"');

    $this->travel(86401)->seconds();

    charge($this, '"abc"')->assertJsonPath('id', 'ch_2');
});

it('ignores methods that are idempotent already', function (): void {
    $this->getJson('api/charges', ['Idempotency-Key' => '"abc"'])->assertJsonPath('run', 1);
    $this->getJson('api/charges', ['Idempotency-Key' => '"abc"'])->assertJsonPath('run', 2);
});

it('passes requests without a key straight through', function (): void {
    $this->postJson('api/charges', ['amount' => 1])->assertCreated();
    $this->postJson('api/charges', ['amount' => 1])->assertCreated();

    expect($this->runs)->toBe(2);
});

it('answers a missing key with 400 where one is required (draft section 2.7)', function (): void {
    $this->postJson('api/strict')
        ->assertStatus(400)
        ->assertProblemType('about:blank', 400)
        ->assertJsonPath('detail', 'This operation requires an Idempotency-Key header.');

    $this->postJson('api/strict', [], ['Idempotency-Key' => '"abc"'])->assertOk();
});

it('answers a malformed key with 400', function (): void {
    charge($this, 'two words')->assertStatus(400);
});
