<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * Guards against the package keeping an application alive after its test, or after its request
 * on a long-running worker: through a closure in a static registry, a static property, or a
 * listener that captures the container. Every feature is enabled (see TestCase::features()).
 *
 * The test framework itself keeps the most recent test, so the application of the boot before
 * the current one may still be reachable; anything older must not be.
 */

final class BootedApplications
{
    /** @var list<WeakReference<object>> */
    public static array $applications = [];

    /** @var list<int> */
    public static array $memory = [];
}

dataset('boots', fn () => range(1, 40));

it('boots and serves requests with every feature enabled', function (int $boot): void {
    BootedApplications::$applications[] = WeakReference::create($this->app);

    Route::get('api/thing', fn () => ['ok' => true])->middleware(['prefer', 'conditional', 'produces:application/json']);

    $this->getJson('api/thing', ['Prefer' => 'return=minimal'])->assertOk();
    $this->getJson('api/missing')->assertNotFound();

    gc_collect_cycles();
    BootedApplications::$memory[] = memory_get_usage();
})->with('boots');

it('lets every earlier application be collected', function (): void {
    gc_collect_cycles();

    $earlier = array_slice(BootedApplications::$applications, 0, -2);
    $alive = array_filter($earlier, static fn (WeakReference $application): bool => $application->get() !== null);

    expect($earlier)->toHaveCount(38)
        ->and($alive)->toBe([]);
});

it('does not grow from one boot to the next', function (): void {
    // The first boots load classes and warm caches; after that, memory should be flat.
    $warm = BootedApplications::$memory[9];
    $last = BootedApplications::$memory[39];

    expect($last - $warm)->toBeLessThan(1024 * 1024);
});
