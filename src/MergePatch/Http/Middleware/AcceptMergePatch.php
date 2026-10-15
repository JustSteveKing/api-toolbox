<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MergePatch\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\MergePatch\Exceptions\UnsupportedPatchType;
use JustSteveKing\Toolbox\MergePatch\MergePatch;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Makes application/merge-patch+json the route's PATCH format.
 *
 *     ->middleware('merge-patch')
 *
 * A PATCH in any other format gets 415 with Accept-Patch (RFC 5789, section 2.2). Every response
 * advertises the format in Accept-Patch, which RFC 5789 section 3.1 says a server may use to show
 * that it supports PATCH and which formats.
 */
final readonly class AcceptMergePatch
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('PATCH')) {
            $field = $request->headers->get('Content-Type');
            $type = $field === null ? null : strtolower(trim(explode(';', $field)[0]));

            if ($type !== MergePatch::MEDIA_TYPE) {
                throw new UnsupportedPatchType($field ?: null);
            }
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        $response->headers->set('Accept-Patch', MergePatch::MEDIA_TYPE);

        return $response;
    }
}
