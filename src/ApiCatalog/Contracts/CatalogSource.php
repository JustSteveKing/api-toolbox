<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ApiCatalog\Contracts;

/**
 * Where the catalog's APIs come from. The default reads the feature's "apis" option; bind your own
 * to build the catalog from a database or an API gateway.
 *
 * Each API is an array keyed by link relation: "href" for the API itself, then any of the
 * RFC 8631 relations, "service-desc", "service-doc", "service-meta" and "status", each a URL or a
 * link target object such as ['href' => '/openapi.json', 'type' => 'application/vnd.oai.openapi+json'].
 */
interface CatalogSource
{
    /**
     * @return list<array<string, mixed>>
     */
    public function apis(): array;

    /**
     * Other API catalogs this one links to (RFC 9727, section 4.3).
     *
     * @return list<string>
     */
    public function catalogs(): array;
}
