<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * 415 (RFC 9110, section 15.5.16), with Accept saying what would have been accepted, as that
 * section suggests, and Accept-Patch as well for a PATCH, which RFC 5789 section 2.2 says such a
 * response SHOULD include.
 */
final class UnsupportedMediaType extends UnsupportedMediaTypeHttpException
{
    /**
     * @param  list<string>  $supported
     */
    public function __construct(?string $received, array $supported, bool $patch)
    {
        $list = implode(', ', $supported);

        parent::__construct(
            $received === null
                ? "The request has content but no Content-Type. Supported content types: {$list}."
                : "The Content-Type '{$received}' is not supported. Supported content types: {$list}.",
            headers: array_filter([
                'Accept' => $list,
                'Accept-Patch' => $patch ? $list : null,
            ], is_string(...)),
        );
    }
}
