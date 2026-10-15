<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing;

use Closure;
use DateTimeInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\TestResponse;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\Testing\Assertions\DeprecationAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\LinkAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\ProblemDetailsAssertions;
use Pest\Expectation;
use PHPUnit\Framework\Assert;

/**
 * TestResponse macros and Pest expectations for each enabled feature.
 *
 * Registered only while the application runs its tests, and only for features that are enabled,
 * so a disabled feature adds no macros here either. Pest expectations are added when Pest is
 * installed; the macros work with PHPUnit alone.
 */
final class TestingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $features = $this->app->make(EnabledFeatures::class);

        if ($features->enabled(Features::PROBLEM_DETAILS)) {
            $this->problemDetails();
        }

        if ($features->enabled(Features::DEPRECATION)) {
            $this->deprecation();
            $this->links();
        }
    }

    private function problemDetails(): void
    {
        TestResponse::macro('assertProblem', function (?int $status = null): TestResponse {
            ProblemDetailsAssertions::assertProblem($this, $status);

            return $this;
        });

        TestResponse::macro('assertProblemType', function (string $type, ?int $status = null): TestResponse {
            ProblemDetailsAssertions::assertProblemType($this, $type, $status);

            return $this;
        });

        $this->expectation('toBeProblem', static fn (TestResponse $response, ?int $status = null) => ProblemDetailsAssertions::assertProblem($response, $status));
        $this->expectation('toHaveProblemType', static fn (TestResponse $response, string $type, ?int $status = null) => ProblemDetailsAssertions::assertProblemType($response, $type, $status));
    }

    private function deprecation(): void
    {
        TestResponse::macro('assertDeprecated', function (DateTimeInterface|string|null $at = null, ?string $link = null): TestResponse {
            DeprecationAssertions::assertDeprecated($this, $at, $link);

            return $this;
        });

        TestResponse::macro('assertNotDeprecated', function (): TestResponse {
            DeprecationAssertions::assertNotDeprecated($this);

            return $this;
        });

        TestResponse::macro('assertSunset', function (DateTimeInterface|string|null $at = null, ?string $link = null): TestResponse {
            DeprecationAssertions::assertSunset($this, $at, $link);

            return $this;
        });

        $this->expectation('toBeDeprecated', static fn (TestResponse $response, DateTimeInterface|string|null $at = null, ?string $link = null) => DeprecationAssertions::assertDeprecated($response, $at, $link));
        $this->expectation('toHaveSunset', static fn (TestResponse $response, DateTimeInterface|string|null $at = null, ?string $link = null) => DeprecationAssertions::assertSunset($response, $at, $link));
    }

    private function links(): void
    {
        TestResponse::macro('assertLinkHeader', function (string $rel, ?string $uri = null): TestResponse {
            LinkAssertions::assertLinkHeader($this, $rel, $uri);

            return $this;
        });

        TestResponse::macro('assertNoLinkHeader', function (string $rel): TestResponse {
            LinkAssertions::assertNoLinkHeader($this, $rel);

            return $this;
        });

        $this->expectation('toHaveLink', static fn (TestResponse $response, string $rel, ?string $uri = null) => LinkAssertions::assertLinkHeader($response, $rel, $uri));
    }

    /**
     * Adds a Pest expectation whose value must be a TestResponse. $assertion takes the response
     * and then whatever arguments the expectation was called with.
     */
    private function expectation(string $name, Closure $assertion): void
    {
        if (! class_exists(Expectation::class)) {
            return;
        }

        expect()->extend($name, function (mixed ...$arguments) use ($name, $assertion): Expectation {
            if (! $this->value instanceof TestResponse) {
                Assert::fail("{$name}() expects a TestResponse.");
            }

            $assertion($this->value, ...$arguments);

            return $this;
        });
    }
}
