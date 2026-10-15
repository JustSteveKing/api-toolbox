<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Negotiation\AcceptHeader;
use JustSteveKing\Toolbox\Negotiation\Exceptions\NotAcceptable;
use JustSteveKing\Toolbox\Negotiation\MediaType;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Answers 406 when the client's Accept rules out every type the route produces.
 *
 *     ->middleware('produces:application/json')
 *
 * Section 12.5.1 lets a server either send 406 or ignore Accept. A route that opts in has chosen
 * strict negotiation. Error responses are not checked: RFC 9457 section 3 notes a problem can be
 * sent as application/problem+json even when the client did not list it.
 */
final readonly class EnsureAcceptable
{
    public function handle(Request $request, Closure $next, string ...$produces): Response
    {
        $available = array_map(
            static fn (string $type): MediaType => MediaType::parse($type) ?? throw new InvalidArgumentException("[{$type}] is not a media type."),
            $produces,
        );

        $accept = AcceptHeader::parse($request->headers->get('Accept'));

        $acceptable = array_filter($available, static fn (MediaType $type): bool => $accept->quality($type) > 0);

        if ($acceptable === []) {
            throw new NotAcceptable(array_values(array_map(static fn (MediaType $type): string => $type->essence(), $available)));
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        // Section 12.5.5: the response depends on Accept, so a cache has to key on it.
        $response->setVary('Accept', false);

        return $response;
    }
}
