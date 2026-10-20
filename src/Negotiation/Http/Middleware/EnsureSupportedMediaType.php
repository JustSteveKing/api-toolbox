<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Negotiation\Exceptions\UnsupportedMediaType;
use JustSteveKing\Toolbox\Negotiation\MediaType;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Answers 415 when a request's content is not in a type the route consumes.
 *
 *     ->middleware('consumes:application/json,application/merge-patch+json')
 *
 * Only requests with content are checked. Content without a Content-Type is refused too: RFC 9110
 * section 8.3 lets a recipient assume application/octet-stream or sniff, and neither is a type
 * the route said it consumes.
 */
final readonly class EnsureSupportedMediaType
{
    public function handle(Request $request, Closure $next, string ...$consumes): Response
    {
        if (self::hasContent($request)) {
            $supported = array_map(
                static fn (string $type): MediaType => MediaType::parse($type) ?? throw new InvalidArgumentException("[{$type}] is not a media type."),
                $consumes,
            );

            // An empty Content-Type says no more than a missing one.
            $field = $request->headers->get('Content-Type') ?: null;
            $received = $field === null ? null : MediaType::parse($field);

            $matches = $received !== null && array_filter($supported, static fn (MediaType $type): bool => $type->covers($received)) !== [];

            if (! $matches) {
                throw new UnsupportedMediaType(
                    $field,
                    array_values(array_map(static fn (MediaType $type): string => $type->essence(), $supported)),
                    $request->isMethod('PATCH'),
                );
            }
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }

    /**
     * The raw body is not enough on its own: PHP parses multipart/form-data into fields and files
     * and leaves php://input empty, so a multipart upload has content only in the parameter bags.
     */
    private static function hasContent(Request $request): bool
    {
        return $request->getContent() !== ''
            || $request->request->count() > 0
            || $request->files->count() > 0;
    }
}
