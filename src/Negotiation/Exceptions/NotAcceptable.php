<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Exceptions;

use JustSteveKing\Toolbox\Support\Messages;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

/**
 * 406 (RFC 9110, section 15.5.7). The section says the content SHOULD list the available
 * representations, which the detail does, the way apiguide.dev's not-acceptable entry shows.
 */
final class NotAcceptable extends NotAcceptableHttpException
{
    /**
     * @param  list<string>  $available
     */
    public function __construct(array $available)
    {
        parent::__construct(
            Messages::get('negotiation.not_acceptable', ['types' => implode(', ', $available)]),
            headers: ['Vary' => 'Accept'],
        );
    }
}
