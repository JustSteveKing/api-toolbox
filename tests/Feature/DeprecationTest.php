<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use JustSteveKing\Toolbox\Deprecation\Notice;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\CurrentController;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\FilteredContactsController;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\LegacyOrdersController;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\MisconfiguredController;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\OffsetController;
use JustSteveKing\Toolbox\Tests\Fixtures\Controllers\PolicyOnlyController;

it('sends the exact header syntax RFC 9745 and RFC 8594 specify', function (): void {
    Route::get('api/orders', [LegacyOrdersController::class, 'index']);

    $response = $this->get('api/orders')->assertOk();

    // RFC 9745 section 2.1, and section 4's dates.
    expect($response->headers->get('Deprecation'))->toBe('@1688169599')
        // RFC 8594 section 3: an IMF-fixdate in GMT.
        ->and($response->headers->get('Sunset'))->toBe('Sun, 30 Jun 2024 23:59:59 GMT')
        // RFC 9745 section 3 and RFC 8594 section 6.
        ->and($response->headers->all('link'))->toBe([
            '<https://developer.example.com/deprecation>; rel="deprecation"',
            '<https://developer.example.com/sunset>; rel="sunset"',
        ]);
});

it('lets an action attribute override the controller one, kind by kind', function (): void {
    Route::get('api/orders/1', [LegacyOrdersController::class, 'show']);

    $response = $this->get('api/orders/1');

    // The action's Deprecation has no link; the controller's Sunset still applies.
    expect($response->headers->get('Deprecation'))->toBe('@1704067200')
        ->and($response->headers->get('Sunset'))->toBe('Sun, 30 Jun 2024 23:59:59 GMT')
        ->and($response->headers->all('link'))->toBe(['<https://developer.example.com/sunset>; rel="sunset"']);
});

it('annotates error responses too, since the headers describe the resource', function (): void {
    Route::post('api/orders', [LegacyOrdersController::class, 'store']);

    $this->postJson('api/orders')
        ->assertStatus(422)
        ->assertHeader('Deprecation', '@1688169599')
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('appends its links to ones the response already has', function (): void {
    Route::get('api/orders/linked', [LegacyOrdersController::class, 'withLink']);

    expect($this->get('api/orders/linked')->headers->all('link'))->toBe([
        '<https://api.example.com/orders?page=2>; rel="next"',
        '<https://developer.example.com/deprecation>; rel="deprecation"',
        '<https://developer.example.com/sunset>; rel="sunset"',
    ]);
});

it('sends only the policy link when nothing is deprecated yet (RFC 9745 section 3.1)', function (): void {
    Route::get('api/current', PolicyOnlyController::class);

    $response = $this->get('api/current');

    $response->assertHeaderMissing('Deprecation')->assertHeaderMissing('Sunset');

    expect($response->headers->all('link'))->toBe(['<https://developer.example.com/deprecation>; rel="deprecation"']);
});

it('sends nothing for a route without attributes', function (): void {
    Route::get('api/current', CurrentController::class);

    $response = $this->get('api/current');

    $response->assertHeaderMissing('Deprecation')->assertHeaderMissing('Sunset')->assertHeaderMissing('Link');
});

it('sends nothing for closure routes', function (): void {
    Route::get('api/closure', fn () => 'closure');

    $this->get('api/closure')->assertHeaderMissing('Deprecation');
});

it('reads dates with an offset correctly and dates without one as UTC', function (): void {
    Route::get('api/offset', OffsetController::class);

    $this->get('api/offset')
        ->assertHeader('Deprecation', '@1782863999')
        ->assertHeader('Sunset', 'Fri, 01 Jan 2027 00:00:00 GMT');
});

it('refuses a sunset earlier than the deprecation (RFC 9745 section 4)', function (): void {
    $this->withoutExceptionHandling();

    Route::get('api/misconfigured', MisconfiguredController::class);

    $this->get('api/misconfigured');
})->throws(InvalidArgumentException::class, 'must not be earlier than deprecation');

it('reads notices from whatever source is bound, which is the seam for a manifest', function (): void {
    app()->instance(DeprecationSource::class, new class implements DeprecationSource
    {
        public function forRoute(RoutingRoute $route): ?Notice
        {
            return $route->uri() === 'api/manifest'
                ? new Notice(deprecatedAt: Notice::date('2023-06-30T23:59:59Z'))
                : null;
        }
    });

    Route::get('api/manifest', fn () => 'from a manifest');

    $this->get('api/manifest')->assertHeader('Deprecation', '@1688169599');
});

it('does not send links twice when it is both global and on the route', function (): void {
    Route::get('api/orders', [LegacyOrdersController::class, 'index'])->middleware('deprecation');

    expect($this->get('api/orders')->headers->all('link'))->toHaveCount(2);
});

it('deprecates a query form of a route on its own, since its URI is a resource of its own', function (): void {
    Route::get('api/contacts', [FilteredContactsController::class, 'index']);

    $filtered = $this->get('api/contacts?organization_id=42')->assertOk();

    expect($filtered->headers->get('Deprecation'))->toBe('@1793232000')
        ->and($filtered->headers->get('Sunset'))->toBe('Sun, 31 Jan 2027 23:59:59 GMT')
        ->and($filtered->headers->all('link'))->toBe(['<https://developer.example.com/filters>; rel="deprecation"']);

    $plain = $this->get('api/contacts?search=ada')->assertOk();

    expect($plain->headers->has('Deprecation'))->toBeFalse()
        ->and($plain->headers->has('Sunset'))->toBeFalse()
        ->and($plain->headers->has('Link'))->toBeFalse();
});

it('prefers a query form\'s notice to the route\'s own, and falls back to it without the query', function (): void {
    Route::get('api/contacts/export', [FilteredContactsController::class, 'export']);

    // The action's own Deprecation applies without the query; with it, the class's scoped one does.
    expect($this->get('api/contacts/export')->headers->get('Deprecation'))->toBe('@1767225600')
        ->and($this->get('api/contacts/export?organization_id=42')->headers->get('Deprecation'))->toBe('@1793232000');
});
