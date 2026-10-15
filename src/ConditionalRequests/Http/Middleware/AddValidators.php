<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Http\Middleware;

use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;
use JustSteveKing\Toolbox\ConditionalRequests\RouteValidators;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Adds ETag and Last-Modified to successful GET and HEAD responses (RFC 9110, section 8.8).
 *
 * Validators come from the route-bound resource when it implements HasValidators, and otherwise
 * the ETag is a hash of the content. A validator the response already has is left alone.
 *
 * Only GET and HEAD: section 9.3.4 says a successful PUT MUST NOT carry a validator unless the
 * content was stored exactly as received, which a package cannot know.
 *
 *     ->middleware('validators')        strong content hash
 *     ->middleware('validators:weak')   weak content hash, for content that can vary trivially
 */
final readonly class AddValidators
{
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        if (! $request->isMethodSafe() || ! $response->isSuccessful()) {
            return $response;
        }

        $resource = RouteValidators::for($request);

        if (! $response->headers->has('ETag')) {
            $entityTag = $resource?->entityTag() ?? $this->forContent($response, in_array('weak', $options, true));

            if ($entityTag !== null) {
                $response->headers->set('ETag', $entityTag->toString());
            }
        }

        $lastModified = $resource?->lastModified();

        if (! $response->headers->has('Last-Modified') && $lastModified instanceof DateTimeInterface) {
            // Section 5.6.7: an IMF-fixdate, always in GMT.
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified->getTimestamp()).' GMT');
        }

        return $response;
    }

    /**
     * Streamed and file responses have no content to hash until they are sent.
     */
    private function forContent(Response $response, bool $weak): ?EntityTag
    {
        $content = $response->getContent();

        return is_string($content) ? EntityTag::forContent($content, $weak) : null;
    }
}
