<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    RateLimiter::for('api', fn () => Limit::perMinute(1));
    Route::get('v1/orders', fn () => ['ok' => true])->middleware('throttle:api');
});

it('drops Laravel\'s X-RateLimit headers when asked to', function (): void {
    $this->getJson('v1/orders')
        ->assertHeader('RateLimit', '"api";r=0;t=60')
        ->assertHeaderMissing('X-RateLimit-Limit')
        ->assertHeaderMissing('X-RateLimit-Remaining');

    $this->getJson('v1/orders')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '60')
        ->assertHeaderMissing('X-RateLimit-Limit')
        ->assertHeaderMissing('X-RateLimit-Reset');
});
