<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Exceptions;

use JustSteveKing\Toolbox\Support\Messages;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 428 Precondition Required (RFC 6585, section 3).
 *
 * The section says the response SHOULD explain how to resubmit successfully, which the message
 * does, and that it MUST NOT be stored by a cache, which no-store tells every cache downstream.
 */
final class PreconditionRequired extends HttpException
{
    public function __construct()
    {
        parent::__construct(
            428,
            Messages::get('conditional_requests.precondition_required'),
            headers: ['Cache-Control' => 'no-store'],
        );
    }
}
