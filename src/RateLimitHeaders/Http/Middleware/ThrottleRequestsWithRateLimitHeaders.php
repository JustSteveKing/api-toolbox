<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\RateLimitHeaders\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Collection;
use JustSteveKing\Toolbox\RateLimitHeaders\PolicyHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel's ThrottleRequests, adding the RateLimit and RateLimit-Policy fields from
 * draft-ietf-httpapi-ratelimit-headers-11. Experimental, as the draft is.
 *
 * Laravel's limiter still decides everything. This only overrides the two methods that know a
 * limit's name and window, which the header methods are never given, and mirrors the parent's
 * logic in them exactly.
 *
 * Bound in the container in place of ThrottleRequests, so every route using `throttle` gets it.
 *
 * @phpstan-type LimitShape object{key: string, maxAttempts: int, decaySeconds: int, afterCallback: (callable(Response): bool)|null, responseCallback: callable|null, policy?: string}
 */
final class ThrottleRequestsWithRateLimitHeaders extends ThrottleRequests
{
    /**
     * The policy name for `throttle:60,1`, which has no name of its own.
     */
    public const string DEFAULT_POLICY = 'default';

    public function __construct(
        RateLimiter $limiter,
        private readonly bool $legacyHeaders = true,
    ) {
        parent::__construct($limiter);
    }

    /**
     * As the parent, but each limit carries a policy name: the limiter's name, numbered when the
     * limiter returns more than one limit.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $limiterName
     */
    protected function handleRequestUsingNamedLimiter($request, Closure $next, $limiterName, Closure $limiter): Response
    {
        $limiterResponse = $limiter($request);

        if ($limiterResponse instanceof Response) {
            return $limiterResponse;
        } elseif ($limiterResponse instanceof Unlimited) {
            /** @var Response */
            return $next($request);
        }

        $limits = array_values(array_filter(Collection::wrap($limiterResponse)->all(), static fn (mixed $limit): bool => $limit instanceof Limit));
        $mapped = [];

        foreach ($limits as $index => $limit) {
            $key = is_scalar($limit->key) ? (string) $limit->key : '';

            $mapped[] = (object) [
                'key' => self::$shouldHashKeys ? md5($limiterName.$key) : $limiterName.':'.$key,
                'maxAttempts' => $limit->maxAttempts,
                'decaySeconds' => $limit->decaySeconds,
                'afterCallback' => $limit->afterCallback,
                'responseCallback' => $limit->responseCallback,
                'policy' => count($limits) === 1 ? $limiterName : $limiterName.'-'.($index + 1),
            ];
        }

        return $this->handleRequest($request, $next, $mapped);
    }

    /**
     * As the parent, then RateLimit and RateLimit-Policy for every limit that applied.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array<int, LimitShape>  $limits
     */
    protected function handleRequest($request, Closure $next, array $limits): Response
    {
        foreach ($limits as $limit) {
            if ($this->limiter->tooManyAttempts($limit->key, $limit->maxAttempts)) {
                throw $this->exceeded($request, $limit, $limits);
            }
        }

        foreach ($limits as $limit) {
            if (! $limit->afterCallback) {
                $this->limiter->hit($limit->key, $limit->decaySeconds);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        foreach ($limits as $limit) {
            if ($limit->afterCallback && ($limit->afterCallback)($response)) {
                $this->limiter->hit($limit->key, $limit->decaySeconds);
            }

            if ($this->legacyHeaders) {
                $response = $this->addHeaders(
                    $response,
                    $limit->maxAttempts,
                    $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts)
                );
            }
        }

        foreach ($this->policyHeaders($limits)->toArray() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * The parent's exception, with the draft's fields added. The 429 also gets no-store, because
     * RFC 6585 section 4 says it MUST NOT be stored by a cache.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  LimitShape  $limit
     * @param  array<int, LimitShape>  $limits
     */
    private function exceeded($request, object $limit, array $limits): ThrottleRequestsException|HttpResponseException
    {
        $exception = $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);

        $headers = [...$this->policyHeaders($limits)->toArray(), 'Cache-Control' => 'no-store'];

        if ($exception instanceof HttpResponseException) {
            $exception->getResponse()->headers->add($headers);

            return $exception;
        }

        $existing = $exception->getHeaders();

        if (! $this->legacyHeaders) {
            unset($existing['X-RateLimit-Limit'], $existing['X-RateLimit-Remaining'], $existing['X-RateLimit-Reset']);
        }

        $exception->setHeaders([...$existing, ...$headers]);

        return $exception;
    }

    /**
     * @param  array<int, LimitShape>  $limits
     */
    private function policyHeaders(array $limits): PolicyHeaders
    {
        $headers = new PolicyHeaders;

        foreach ($limits as $limit) {
            $hits = $this->limiter->attempts($limit->key);
            $hits = is_numeric($hits) ? (int) $hits : 0;

            $headers->add(
                policy: $limit->policy ?? self::DEFAULT_POLICY,
                quota: $limit->maxAttempts,
                window: $limit->decaySeconds,
                remaining: max(0, $limit->maxAttempts - $hits),
                // No hits means no window has started, so there is no effective window to report.
                resetsIn: $hits > 0 ? $this->limiter->availableIn($limit->key) : null,
            );
        }

        return $headers;
    }
}
