<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Deprecation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use JustSteveKing\Toolbox\Deprecation\Notice;
use JustSteveKing\Toolbox\Support\Link;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Adds Deprecation (RFC 9745), Sunset (RFC 8594) and their Link relations to the response.
 *
 * Runs after the route has handled the request, so it also annotates error responses: both
 * headers describe the resource, not the outcome of one request (RFC 9745 section 2, RFC 8594
 * section 3).
 */
final readonly class AddDeprecationHeaders
{
    private const string APPLIED = '_api_toolbox_deprecation_applied';

    public function __construct(
        private DeprecationSource $source,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        $route = $request->route();

        // Registered globally and by alias at once, this would otherwise append every Link twice.
        if (! $route instanceof Route || $request->attributes->get(self::APPLIED) === true) {
            return $response;
        }

        $request->attributes->set(self::APPLIED, true);

        $notice = $this->source->forRoute($route);

        if ($notice !== null) {
            $this->apply($notice, $response);
        }

        return $response;
    }

    private function apply(Notice $notice, Response $response): void
    {
        // RFC 9745, section 2.1: an sf-date, "@" followed by an integer of seconds since the epoch.
        if ($notice->deprecatedAt !== null) {
            $response->headers->set('Deprecation', '@'.$notice->deprecatedAt->getTimestamp());
        }

        // RFC 8594, section 3: an HTTP-date, which RFC 9110 section 5.6.7 fixes as IMF-fixdate in
        // GMT. RFC 9745's own section 4 example writes "UTC" instead; that is not a valid
        // HTTP-date, so it is not followed here.
        if ($notice->sunsetAt !== null) {
            $response->headers->set('Sunset', gmdate('D, d M Y H:i:s', $notice->sunsetAt->getTimestamp()).' GMT');
        }

        // Appended rather than set, so links other code has already added survive.
        if ($notice->deprecationLink !== null) {
            $response->headers->set('Link', (new Link($notice->deprecationLink, 'deprecation'))->toHeaderValue(), false);
        }

        if ($notice->sunsetLink !== null) {
            $response->headers->set('Link', (new Link($notice->sunsetLink, 'sunset'))->toHeaderValue(), false);
        }
    }
}
