<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\Str;
use JustSteveKing\Toolbox\Negotiation\CurrentRequestId;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Gives every request an identifier, on the response and in the log context.
 *
 * No standard defines a request ID header. X-Request-Id is the convention most proxies, load
 * balancers and platforms already use, so it is the default; the name is configurable. This is
 * the one part of the package that is not built on a published standard, kept because a request
 * ID is how a support ticket, a log line and a response are tied together.
 *
 * An incoming ID is kept when it is a short run of visible ASCII, so one ID can follow a request
 * through a proxy. Anything else is replaced with a UUIDv7, whose time ordering keeps IDs roughly
 * sorted in logs.
 */
final readonly class AssignRequestId
{
    public const string CONTEXT_KEY = 'request_id';

    public function __construct(
        private Context $context,
        private CurrentRequestId $current,
        private string $header = 'X-Request-Id',
        private bool $trustIncoming = true,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get($this->header);

        $id = $this->trustIncoming && is_string($incoming) && preg_match('/^[\x21-\x7E]{1,200}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid7();

        $request->headers->set($this->header, $id);
        $this->context->add(self::CONTEXT_KEY, $id);
        $this->current->id = $id;

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        $response->headers->set($this->header, $id);

        return $response;
    }
}
