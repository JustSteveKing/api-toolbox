<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

/**
 * A list whose filter is being retired, and an action that is deprecated whole as well.
 */
#[Deprecation(at: '2026-10-29T00:00:00Z', link: 'https://developer.example.com/filters', query: 'organization_id')]
#[Sunset(at: '2027-01-31T23:59:59Z', query: 'organization_id')]
final class FilteredContactsController
{
    public function index(): string
    {
        return 'contacts';
    }

    #[Deprecation(at: '2026-01-01T00:00:00Z', link: 'https://developer.example.com/export')]
    public function export(): string
    {
        return 'export';
    }
}
