<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use JustSteveKing\Toolbox\Testing\TestingServiceProvider;

it('registers no testing helpers outside of tests', function (): void {
    expect(app()->runningUnitTests())->toBeFalse()
        ->and(app()->getProviders(TestingServiceProvider::class))->toBe([])
        ->and(TestResponse::hasMacro('assertProblem'))->toBeFalse()
        ->and(TestResponse::hasMacro('assertDeprecated'))->toBeFalse();
});
