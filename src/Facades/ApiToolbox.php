<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Facades;

use Illuminate\Support\Facades\Facade;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;

/**
 * Runtime feature checks for applications. Package internals inject EnabledFeatures instead.
 *
 * @method static bool enabled(string $feature)
 * @method static array<string, mixed> options(string $feature)
 * @method static list<string> all()
 *
 * @see EnabledFeatures
 */
final class ApiToolbox extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EnabledFeatures::class;
    }
}
