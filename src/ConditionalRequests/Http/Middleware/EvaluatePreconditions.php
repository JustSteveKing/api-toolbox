<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;
use JustSteveKing\Toolbox\ConditionalRequests\Exceptions\PreconditionFailed;
use JustSteveKing\Toolbox\ConditionalRequests\Outcome;
use JustSteveKing\Toolbox\ConditionalRequests\Preconditions;
use JustSteveKing\Toolbox\ConditionalRequests\ReceivedDate;
use JustSteveKing\Toolbox\ConditionalRequests\RouteValidators;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Evaluates If-Match, If-None-Match, If-Modified-Since and If-Unmodified-Since (RFC 9110,
 * section 13).
 *
 * Unsafe methods are evaluated before the action runs, against the route-bound resource, because
 * section 13.1.1 says the method MUST NOT be performed when the condition is false. GET and HEAD
 * are evaluated against the response the action produced: running a safe method has no effect to
 * prevent, and only the finished response knows every header a 304 has to repeat (section 15.4.5).
 */
final readonly class EvaluatePreconditions
{
    public function handle(Request $request, Closure $next): Response
    {
        // Section 13.2.1: ignored for methods that do not select or modify a representation.
        if (in_array($request->getMethod(), ['OPTIONS', 'CONNECT', 'TRACE'], true)) {
            return $this->pass($request, $next);
        }

        if ($request->isMethodSafe()) {
            return $this->afterSafeMethod($request, $this->pass($request, $next));
        }

        $resource = RouteValidators::for($request);
        $current = $resource?->entityTag();

        // A route with no bound resource has no current representation, so "If-Match: *" fails
        // and "If-None-Match: *" passes, which is what lets a PUT create without overwriting.
        $outcome = Preconditions::evaluate($request, $current, $resource?->lastModified(), exists: $resource !== null);

        if ($outcome !== Outcome::Proceed) {
            throw new PreconditionFailed($current);
        }

        return $this->pass($request, $next);
    }

    private function afterSafeMethod(Request $request, Response $response): Response
    {
        // Section 13.2.1: preconditions are ignored when the response would not have been 2xx.
        if (! $response->isSuccessful()) {
            return $response;
        }

        $current = EntityTag::parse((string) $response->headers->get('ETag'));

        $outcome = Preconditions::evaluate(
            $request,
            $current,
            ReceivedDate::parse($response->headers->get('Last-Modified')),
        );

        return match ($outcome) {
            Outcome::Proceed => $response,
            Outcome::NotModified => $this->notModified($response),
            Outcome::PreconditionFailed => throw new PreconditionFailed($current),
        };
    }

    /**
     * Section 15.4.5: a 304 keeps Content-Location, Date, ETag, Vary, Cache-Control and Expires,
     * and has no content. Symfony's setNotModified() does exactly that.
     *
     * It also drops Last-Modified, which the section says "might be useful" when there is no ETag.
     * Restoring it here would not survive: Laravel's Router::toResponse() calls setNotModified()
     * again on every 304 once route middleware has run. Leaving it out is still compliant, since
     * the section only says a sender SHOULD NOT send other metadata unless it helps.
     */
    private function notModified(Response $response): Response
    {
        return $response->setNotModified();
    }

    private function pass(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }
}
