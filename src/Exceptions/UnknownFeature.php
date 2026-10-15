<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Exceptions;

use InvalidArgumentException;
use JustSteveKing\Toolbox\Features;

final class UnknownFeature extends InvalidArgumentException
{
    public static function named(string $feature): self
    {
        return new self(sprintf(
            'Unknown feature [%s] in %s.features. Known features: %s.',
            $feature,
            Features::CONFIG_KEY,
            implode(', ', Features::all()),
        ));
    }
}
