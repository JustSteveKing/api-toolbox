<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
 * A route that declares its own consumes overrides one from its group: only the last declaration
 * the route lists applies, and group middleware is listed before the route's own. So an API can
 * put consumes:application/json on its whole group and let a PATCH route consume
 * application/merge-patch+json instead, without lifting the group's declaration first.
 *
 * Only requests with content are checked. Content without a Content-Type is refused too: RFC 9110
 * section 8.3 lets a recipient assume application/octet-stream or sniff, and neither is a type
 * the route said it consumes.
 */
final readonly class EnsureSupportedMediaType
{
    public function handle(Request $request, Closure $next, string ...$consumes): Response
    {
        if (self::hasContent($request) && self::applies($request, $consumes)) {
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
     * Whether this declaration is the route's last. A route gathers its group's middleware before
     * its own and its controller's, so the last is the most specific. One the route excluded with
     * withoutMiddleware() never runs, so it is not counted.
     *
     * @param  array<array-key, string>  $consumes
     */
    private static function applies(Request $request, array $consumes): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return true;
        }

        $excluded = $route->excludedMiddleware();
        $last = null;

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || in_array($middleware, $excluded, true)) {
                continue;
            }

            [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, '');

            if ($name === 'consumes' || $name === self::class) {
                $last = $parameters;
            }
        }

        return $last === null || explode(',', $last) === array_values($consumes);
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
