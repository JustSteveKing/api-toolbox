<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Pagination;

use Illuminate\Contracts\Pagination\CursorPaginator;
use JustSteveKing\Toolbox\Support\Link;

/**
 * The Link header values for a cursor page (RFC 8288), using the registered relation types
 * "next", "prev" and "first" from the IANA Link Relations registry.
 *
 * There is no "last": a cursor only knows its neighbours, never where the end is.
 */
final class PaginationLinks
{
    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  CursorPaginator<TKey, TValue>  $paginator
     * @return list<Link>
     */
    public static function for(CursorPaginator $paginator): array
    {
        $links = [];

        if (($next = $paginator->nextPageUrl()) !== null) {
            $links[] = new Link($next, 'next');
        }

        if (($previous = $paginator->previousPageUrl()) !== null) {
            $links[] = new Link($previous, 'prev');
        }

        // The page with no cursor at all. Laravel builds it as path plus "?" plus the query, so a
        // page with no other query parameters would end in a bare "?".
        $links[] = new Link(rtrim($paginator->url(null), '?'), 'first');

        return $links;
    }
}
