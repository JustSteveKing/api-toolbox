<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation;

/**
 * The trace the current request belongs to, for code that runs later in the request, such as
 * outgoing HTTP calls. Bound as scoped, so it is reset between requests under Octane.
 */
final class CurrentTrace
{
    public ?TraceContext $context = null;

    /**
     * Section 3.3: tracestate travels with a valid traceparent, unmodified by this package.
     */
    public ?string $state = null;
}
