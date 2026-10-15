<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\RateLimitHeaders\Http\Middleware\ThrottleRequestsWithRateLimitHeaders;

beforeEach(function (): void {
    RateLimiter::for('api', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    RateLimiter::for('layered', fn (Request $request) => [
        Limit::perMinute(10)->by('minute:'.$request->ip()),
        Limit::perHour(100)->by('hour:'.$request->ip()),
    ]);
    RateLimiter::for('custom', fn (Request $request) => Limit::perMinute(1)->by($request->ip())
        ->response(fn (Request $request, array $headers) => response('slow down', 429, $headers)));
    RateLimiter::for('open', fn () => Limit::none());

    Route::get('api/orders', fn () => ['ok' => true])->middleware('throttle:api');
    Route::get('api/layered', fn () => ['ok' => true])->middleware('throttle:layered');
    Route::get('api/custom', fn () => ['ok' => true])->middleware('throttle:custom');
    Route::get('api/open', fn () => ['ok' => true])->middleware('throttle:open');
    Route::get('api/unnamed', fn () => ['ok' => true])->middleware('throttle:5,1');
});

it('replaces Laravel\'s throttle middleware without replacing its limiter', function (): void {
    expect(app(ThrottleRequests::class))->toBeInstanceOf(ThrottleRequestsWithRateLimitHeaders::class);
});

it('sends both fields in the draft\'s exact syntax', function (): void {
    $this->getJson('api/orders')
        ->assertOk()
        ->assertHeader('RateLimit-Policy', '"api";q=3;w=60')
        ->assertHeader('RateLimit', '"api";r=2;t=60')
        ->assertRateLimitHeaders('api', remaining: 2);
});

it('counts down', function (): void {
    $this->getJson('api/orders');
    $this->getJson('api/orders');

    $this->getJson('api/orders')->assertHeader('RateLimit', '"api";r=0;t=60');
});

it('answers 429 with the apiguide.dev type, Retry-After, the fields and no-store', function (): void {
    foreach (range(1, 3) as $request) {
        $this->getJson('api/orders');
    }

    $this->getJson('api/orders')
        ->assertStatus(429)
        ->assertProblemType('https://apiguide.dev/errors/rate-limit-exceeded', 429)
        ->assertHeader('Retry-After', '60')
        ->assertHeader('RateLimit', '"api";r=0;t=60')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertRateLimitHeaders('api', remaining: 0);
});

it('does not let Retry-After fall before the end of the effective window (draft section 6)', function (): void {
    foreach (range(1, 3) as $request) {
        $this->getJson('api/orders');
    }

    $this->travel(20)->seconds();

    $response = $this->getJson('api/orders')->assertStatus(429);

    expect($response->headers->get('Retry-After'))->toBe('40')
        ->and($response->headers->get('RateLimit'))->toBe('"api";r=0;t=40');
});

it('describes every limit a named limiter returns, numbered', function (): void {
    $this->getJson('api/layered')
        ->assertHeader('RateLimit-Policy', '"layered-1";q=10;w=60, "layered-2";q=100;w=3600')
        ->assertHeader('RateLimit', '"layered-1";r=9;t=60, "layered-2";r=99;t=3600')
        ->assertRateLimitHeaders('layered-2', remaining: 99);
});

it('names the policy of an unnamed throttle "default"', function (): void {
    $this->getJson('api/unnamed')->assertHeader('RateLimit-Policy', '"default";q=5;w=60');
});

it('adds the fields to a custom limiter response', function (): void {
    $this->getJson('api/custom');

    $this->getJson('api/custom')
        ->assertStatus(429)
        ->assertContent('slow down')
        ->assertHeader('RateLimit', '"custom";r=0;t=60');
});

it('sends nothing for an unlimited limiter', function (): void {
    $this->getJson('api/open')->assertOk()->assertHeaderMissing('RateLimit')->assertHeaderMissing('RateLimit-Policy');
});

it('keeps Laravel\'s X-RateLimit headers by default, for clients that read them', function (): void {
    $this->getJson('api/orders')
        ->assertHeader('X-RateLimit-Limit', '3')
        ->assertHeader('X-RateLimit-Remaining', '2');
});
