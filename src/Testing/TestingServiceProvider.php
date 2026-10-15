<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing;

use Closure;
use DateTimeInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\TestResponse;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\Testing\Assertions\CacheControlAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\ConditionalRequestAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\DeprecationAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\IdempotencyAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\LinkAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\NegotiationAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\PreferenceAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\ProblemDetailsAssertions;
use JustSteveKing\Toolbox\Testing\Assertions\RateLimitAssertions;
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
        }

        if ($features->enabled(Features::CONDITIONAL_REQUESTS)) {
            $this->conditionalRequests();
        }

        if ($features->enabled(Features::PREFERENCES) || $features->enabled(Features::ASYNC_OPERATIONS)) {
            $this->preferences();
        }

        if ($features->enabled(Features::IDEMPOTENCY)) {
            $this->idempotency($features);
        }

        if ($features->enabled(Features::RATE_LIMIT_HEADERS)) {
            $this->rateLimitHeaders();
        }

        if ($features->enabled(Features::NEGOTIATION)) {
            $this->negotiation($features);
        }

        // Link headers come from more than one feature.
        if ($features->enabled(Features::DEPRECATION) || $features->enabled(Features::PAGINATION)) {
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

    private function conditionalRequests(): void
    {
        TestResponse::macro('assertETag', function (?string $etag = null, ?bool $weak = null): TestResponse {
            ConditionalRequestAssertions::assertETag($this, $etag, $weak);

            return $this;
        });

        TestResponse::macro('assertNotModified', function (): TestResponse {
            ConditionalRequestAssertions::assertNotModified($this);

            return $this;
        });

        TestResponse::macro('assertPreconditionFailed', function (): TestResponse {
            ConditionalRequestAssertions::assertPreconditionFailed($this);

            return $this;
        });

        TestResponse::macro('assertPreconditionRequired', function (): TestResponse {
            ConditionalRequestAssertions::assertPreconditionRequired($this);

            return $this;
        });

        $this->expectation('toHaveETag', static fn (TestResponse $response, ?string $etag = null, ?bool $weak = null) => ConditionalRequestAssertions::assertETag($response, $etag, $weak));
        $this->expectation('toBeNotModified', static fn (TestResponse $response) => ConditionalRequestAssertions::assertNotModified($response));
        $this->expectation('toBePreconditionFailed', static fn (TestResponse $response) => ConditionalRequestAssertions::assertPreconditionFailed($response));
        TestResponse::macro('assertCacheControl', function (array $directives): TestResponse {
            CacheControlAssertions::assertCacheControl($this, $directives);

            return $this;
        });

        $this->expectation('toHaveCacheControl', static fn (TestResponse $response, array $directives) => CacheControlAssertions::assertCacheControl($response, $directives));
        $this->expectation('toBePreconditionRequired', static fn (TestResponse $response) => ConditionalRequestAssertions::assertPreconditionRequired($response));
    }

    private function preferences(): void
    {
        TestResponse::macro('assertPreferenceApplied', function (string $preference, ?string $value = null): TestResponse {
            PreferenceAssertions::assertPreferenceApplied($this, $preference, $value);

            return $this;
        });

        TestResponse::macro('assertPreferenceNotApplied', function (string $preference): TestResponse {
            PreferenceAssertions::assertPreferenceNotApplied($this, $preference);

            return $this;
        });

        TestResponse::macro('assertOperationAccepted', function (): string {
            return PreferenceAssertions::assertOperationAccepted($this);
        });

        $this->expectation('toHavePreferenceApplied', static fn (TestResponse $response, string $preference, ?string $value = null) => PreferenceAssertions::assertPreferenceApplied($response, $preference, $value));
    }

    private function idempotency(EnabledFeatures $features): void
    {
        // A replay is a new message, so it carries its own request ID.
        $requestId = $features->enabled(Features::NEGOTIATION)
            ? ($features->options(Features::NEGOTIATION)['request_id_header'] ?? 'X-Request-Id')
            : null;
        $perMessage = is_string($requestId) ? [$requestId] : [];

        TestResponse::macro('assertIdempotentReplay', function (TestResponse $original) use ($perMessage): TestResponse {
            IdempotencyAssertions::assertIdempotentReplay($this, $original, $perMessage);

            return $this;
        });

        $this->expectation('toBeIdempotentReplayOf', static fn (TestResponse $response, TestResponse $original) => IdempotencyAssertions::assertIdempotentReplay($response, $original, $perMessage));
    }

    private function rateLimitHeaders(): void
    {
        TestResponse::macro('assertRateLimitHeaders', function (?string $policy = null, ?int $remaining = null): TestResponse {
            RateLimitAssertions::assertRateLimitHeaders($this, $policy, $remaining);

            return $this;
        });

        $this->expectation('toHaveRateLimitHeaders', static fn (TestResponse $response, ?string $policy = null, ?int $remaining = null) => RateLimitAssertions::assertRateLimitHeaders($response, $policy, $remaining));
    }

    private function negotiation(EnabledFeatures $features): void
    {
        $header = $features->options(Features::NEGOTIATION)['request_id_header'] ?? 'X-Request-Id';
        $header = is_string($header) ? $header : 'X-Request-Id';

        TestResponse::macro('assertTraceparent', function (?string $traceId = null): TestResponse {
            NegotiationAssertions::assertTraceparent($this, $traceId);

            return $this;
        });

        TestResponse::macro('assertRequestId', function (?string $id = null) use ($header): TestResponse {
            NegotiationAssertions::assertRequestId($this, $header, $id);

            return $this;
        });

        $this->expectation('toHaveTraceparent', static fn (TestResponse $response, ?string $traceId = null) => NegotiationAssertions::assertTraceparent($response, $traceId));
        $this->expectation('toHaveRequestId', static fn (TestResponse $response, ?string $id = null) => NegotiationAssertions::assertRequestId($response, $header, $id));
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
