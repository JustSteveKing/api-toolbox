<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ConditionalRequests\Exceptions\PreconditionRequired;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Answers 428 Precondition Required (RFC 6585, section 3) to an unsafe request with no
 * precondition, so clients cannot overwrite a resource without saying which version they read.
 *
 *     ->middleware('preconditions.required')               PUT, PATCH and DELETE
 *     ->middleware('preconditions.required:PUT,POST')       the methods you name
 *
 * If-Match, If-None-Match and If-Unmodified-Since all count. If-Modified-Since does not: section
 * 13.1.3 of RFC 9110 says to ignore it on anything but GET and HEAD.
 */
final readonly class RequirePreconditions
{
    private const array DEFAULT_METHODS = ['PUT', 'PATCH', 'DELETE'];

    private const array PRECONDITIONS = ['If-Match', 'If-None-Match', 'If-Unmodified-Since'];

    public function handle(Request $request, Closure $next, string ...$methods): Response
    {
        $methods = $methods === [] ? self::DEFAULT_METHODS : array_map(strtoupper(...), $methods);

        if (in_array($request->getMethod(), $methods, true) && ! $this->isConditional($request)) {
            throw new PreconditionRequired;
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }

    private function isConditional(Request $request): bool
    {
        foreach (self::PRECONDITIONS as $header) {
            if ($request->headers->has($header)) {
                return true;
            }
        }

        return false;
    }
}
