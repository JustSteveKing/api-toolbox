<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as Context;
use JustSteveKing\Toolbox\Negotiation\CurrentTrace;
use JustSteveKing\Toolbox\Negotiation\TraceContext;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Joins the request to its W3C trace (Trace Context Level 1, section 4), or starts one.
 *
 * The trace and this operation's span go into the log context, which Laravel also carries into
 * queued jobs. Outgoing HTTP client requests carry the trace on, which is the propagation the
 * specification describes.
 *
 * The response gets a traceparent only when the request brought a valid one. Level 1 defines
 * traceparent as a request header; section 6.3 notes vendors do send it on responses, and asks
 * that they only do so to systems that took part in the trace.
 */
final readonly class PropagateTraceContext
{
    public const string TRACE_ID = 'trace_id';

    public const string SPAN_ID = 'span_id';

    public function __construct(
        private Context $context,
        private CurrentTrace $current,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = TraceContext::parse($request->headers->get('traceparent'));

        // Section 4.3: parent-id becomes the current operation. Section 4.2: no traceparent, or
        // one that does not parse, starts a new trace and discards tracestate.
        $trace = $incoming?->continue() ?? TraceContext::start();

        $this->current->context = $trace;
        $this->current->state = $incoming === null ? null : $request->headers->get('tracestate');

        $this->context->add(self::TRACE_ID, $trace->traceId);
        $this->context->add(self::SPAN_ID, $trace->parentId);

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        if ($incoming !== null) {
            $response->headers->set('traceparent', $trace->toHeaderValue());
        }

        return $response;
    }
}
