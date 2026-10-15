<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MergePatch\Exceptions;

use JustSteveKing\Toolbox\MergePatch\MergePatch;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * RFC 5789 section 2.2: a patch document format the server does not support is answered with
 * 415, and the response SHOULD carry Accept-Patch naming the formats it does.
 */
final class UnsupportedPatchType extends UnsupportedMediaTypeHttpException
{
    public function __construct(?string $received)
    {
        parent::__construct(
            $received === null
                ? 'A PATCH needs a Content-Type of '.MergePatch::MEDIA_TYPE.'.'
                : "The patch format '{$received}' is not supported. Send ".MergePatch::MEDIA_TYPE.'.',
            headers: ['Accept-Patch' => MergePatch::MEDIA_TYPE],
        );
    }
}
