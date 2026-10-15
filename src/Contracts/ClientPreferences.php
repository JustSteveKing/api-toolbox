<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Contracts;

/**
 * The client's RFC 7240 preferences for the current request, for features that act on them
 * without depending on the Preferences feature. Bound only while that feature is enabled.
 */
interface ClientPreferences
{
    /**
     * Whether the client asked for $preference, with $value if one is given.
     */
    public function prefers(string $preference, ?string $value = null): bool;

    /**
     * Records that a preference was honoured, for the Preference-Applied response field.
     */
    public function applied(string $preference, ?string $value = null): void;
}
