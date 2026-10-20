<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Pagination\Exceptions;

use JustSteveKing\Toolbox\Contracts\HasProblemType;
use JustSteveKing\Toolbox\Support\ApiGuide;
use JustSteveKing\Toolbox\Support\Messages;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A cursor that does not decode. Laravel treats one as no cursor at all and serves the first
 * page, which a client paging through results has no way to notice.
 */
final class InvalidCursor extends BadRequestHttpException implements HasProblemType
{
    public function __construct(string $parameter)
    {
        parent::__construct(Messages::get('pagination.invalid_cursor', ['parameter' => $parameter]));
    }

    public function problemType(): string
    {
        return ApiGuide::INVALID_PAGINATION_CURSOR;
    }

    public function problemTitle(): string
    {
        return 'Invalid Pagination Cursor';
    }
}
