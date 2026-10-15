<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MergePatch\Exceptions;

use JustSteveKing\Toolbox\Contracts\HasProblemType;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A merge patch that is not JSON, typed as apiguide.dev's malformed-request-body: the server
 * cannot read the patch at all, so there is nothing to validate.
 */
final class MalformedMergePatch extends BadRequestHttpException implements HasProblemType
{
    public function __construct(string $reason)
    {
        parent::__construct("The merge patch could not be parsed as JSON: {$reason}.");
    }

    public function problemType(): string
    {
        return ApiGuide::MALFORMED_REQUEST_BODY;
    }

    public function problemTitle(): string
    {
        return 'Malformed Request Body';
    }
}
