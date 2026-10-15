<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

enum Outcome
{
    case Proceed;
    case NotModified;
    case PreconditionFailed;
}
