<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support\StructuredFields;

/**
 * An Inner List (RFC 9651, section 3.1.1): a list of Items, with parameters of its own.
 *
 * @phpstan-import-type BareItem from Item
 */
final readonly class InnerList
{
    /**
     * @param  list<Item>  $items
     * @param  array<string, BareItem>  $parameters
     */
    public function __construct(
        public array $items,
        public array $parameters = [],
    ) {}
}
