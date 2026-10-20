<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

use JustSteveKing\Toolbox\Pagination\CursorPage;
use JustSteveKing\Toolbox\Tests\Fixtures\Models\Document;

/**
 * Analysed rather than run: Eloquent's cursorPaginate() returns a paginator with int keys, and
 * CursorPage has to accept it as it is.
 */
final class CursorController
{
    /**
     * @return CursorPage<int, Document>
     */
    public function __invoke(): CursorPage
    {
        return CursorPage::of(Document::query()->orderBy('id')->cursorPaginate(10));
    }
}
