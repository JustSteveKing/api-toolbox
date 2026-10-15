<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Pagination;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Pagination\Exceptions\InvalidCursor;

final class PaginationServiceProvider extends ServiceProvider
{
    /**
     * Replaces Laravel's cursor resolver with one that rejects a cursor it cannot decode, before
     * any query runs, rather than quietly serving the first page. A missing or empty cursor is
     * still the first page.
     */
    public function boot(): void
    {
        // The resolver is static and outlives the application, so it looks the request up when it
        // runs rather than holding on to the application that registered it.
        CursorPaginator::currentCursorResolver(static function (string $name = 'cursor'): ?Cursor {
            $request = Container::getInstance()->make('request');
            $value = $request instanceof Request ? $request->input($name) : null;

            if ($value === null || $value === '') {
                return null;
            }

            // An array (?cursor[]=x) is no more decodable than a garbled string.
            return (is_string($value) ? Cursor::fromEncoded($value) : null) ?? throw new InvalidCursor($name);
        });
    }
}
