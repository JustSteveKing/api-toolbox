<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ApiCatalog\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ApiCatalog\ApiCatalog;
use JustSteveKing\Toolbox\Support\Link;

/**
 * GET and HEAD for /.well-known/api-catalog (RFC 9727, section 2).
 *
 * The document is application/linkset+json with the profile section 4.2 says it SHOULD carry.
 * Section 2 says HEAD SHALL be answered with a Link field using the api-catalog relation; Laravel
 * answers HEAD from the GET route, so every response carries it.
 */
final readonly class ShowApiCatalog
{
    public function __construct(
        private ApiCatalog $catalog,
        private int $maxAge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $url = $request->url();

        $response = new JsonResponse($this->catalog->document($url), 200, [
            'Content-Type' => ApiCatalog::MEDIA_TYPE.'; profile="'.ApiCatalog::PROFILE.'"',
            'Link' => (new Link($url, 'api-catalog'))->toHeaderValue(),
        ], JSON_UNESCAPED_SLASHES);

        // Section 5.3: publishers SHOULD consider caching a catalog.
        if ($this->maxAge > 0) {
            $response->setPublic()->setMaxAge($this->maxAge);
        }

        return $response;
    }
}
