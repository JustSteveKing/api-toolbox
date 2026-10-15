<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ApiCatalog;

use JustSteveKing\Toolbox\ApiCatalog\Contracts\CatalogSource;

final readonly class ConfiguredCatalog implements CatalogSource
{
    /**
     * @param  list<array<string, mixed>>  $apis
     * @param  list<string>  $catalogs
     */
    public function __construct(
        private array $apis = [],
        private array $catalogs = [],
    ) {}

    public function apis(): array
    {
        return $this->apis;
    }

    public function catalogs(): array
    {
        return $this->catalogs;
    }
}
