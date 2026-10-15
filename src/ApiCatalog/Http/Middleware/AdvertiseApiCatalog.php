<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ApiCatalog\Http\Middleware;

use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ApiCatalog\ApiCatalogServiceProvider;
use JustSteveKing\Toolbox\Support\Link;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Points to the API catalog from other responses with Link rel="api-catalog", as RFC 9727
 * section 3 shows for a publisher's home page.
 *
 *     Route::get('/api', ApiRoot::class)->middleware('api-catalog.link');
 */
final readonly class AdvertiseApiCatalog
{
    public function __construct(
        private UrlGenerator $urls,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        $response->headers->set('Link', (new Link($this->urls->route(ApiCatalogServiceProvider::ROUTE), 'api-catalog'))->toHeaderValue(), false);

        return $response;
    }
}
