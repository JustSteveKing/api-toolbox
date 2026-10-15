<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Idempotency\IdempotencyStore;

beforeEach(function (): void {
    Route::post('v1/strict', fn () => 'created')->middleware('idempotent:required');
});

it('links a missing key to the documentation, as the draft\'s section 2.7 example does', function (): void {
    $this->postJson('v1/strict')
        ->assertStatus(400)
        ->assertLinkHeader('describedby', 'https://docs.example.com/idempotency');
});

it('keeps responses for the configured TTL', function (): void {
    expect(app(IdempotencyStore::class)->ttl())->toBe(60);

    $first = $this->postJson('v1/strict', [], ['Idempotency-Key' => '"abc"']);
    $this->postJson('v1/strict', [], ['Idempotency-Key' => '"abc"'])->assertIdempotentReplay($first);
});

it('sends the configured Retry-After while a request is in flight', function (): void {
    $lock = app(IdempotencyStore::class)->lock(hash('sha256', "ip:127.0.0.1\nabc"));
    $lock->get();

    $this->postJson('v1/strict', [], ['Idempotency-Key' => '"abc"'])->assertStatus(409)->assertHeader('Retry-After', '5');

    $lock->release();
});
