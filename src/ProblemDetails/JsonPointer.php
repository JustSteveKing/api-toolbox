<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

/**
 * RFC 6901 JSON Pointers, in the URI fragment form RFC 9457 uses in its validation example.
 */
final class JsonPointer
{
    /**
     * Laravel's validation key "items.0.name" becomes "#/items/0/name".
     *
     * Each segment escapes "~" as "~0" and "/" as "~1" (RFC 6901, section 3), then is
     * percent-encoded for use in a fragment (section 6).
     */
    public static function fromDotPath(string $path): string
    {
        $segments = array_map(
            static fn (string $segment): string => rawurlencode(str_replace(['~', '/'], ['~0', '~1'], $segment)),
            explode('.', $path),
        );

        return '#/'.implode('/', $segments);
    }
}
