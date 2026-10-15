<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('JustSteveKing\Toolbox')
    ->toUseStrictTypes();

arch('classes are final unless they are extension points')
    ->expect('JustSteveKing\Toolbox')
    ->classes()
    ->toBeFinal()
    ->ignoring('JustSteveKing\Toolbox\ProblemDetails\ProblemException');

arch('internals inject contracts rather than calling facades')
    ->expect('JustSteveKing\Toolbox')
    ->not->toUse('Illuminate\Support\Facades')
    ->ignoring('JustSteveKing\Toolbox\Facades');

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

/*
 * Each feature lives in its own namespace so it could be extracted later. A feature may use the
 * package core and Support, never another feature or the testing layer; cross-feature needs go
 * through a contract in the core.
 */
$features = [
    'JustSteveKing\Toolbox\ProblemDetails',
    'JustSteveKing\Toolbox\Deprecation',
];

foreach ($features as $feature) {
    arch("{$feature} depends on no other feature")
        ->expect($feature)
        ->not->toUse([...array_diff($features, [$feature]), 'JustSteveKing\Toolbox\Testing']);
}

arch('the testing layer reads responses, not feature internals')
    ->expect('JustSteveKing\Toolbox\Testing')
    ->not->toUse($features);
