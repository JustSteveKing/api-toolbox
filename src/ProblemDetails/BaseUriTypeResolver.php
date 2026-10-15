<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;

/**
 * Resolves slugs against a base URI. Absolute URIs and full paths pass through untouched.
 *
 * Section 3.1.1 RECOMMENDS absolute type URIs, and says relative ones SHOULD carry the full path
 * because a bare relative reference resolves against each request's own URL. So the default base
 * is the path "/problems" rather than nothing: an unconfigured app emits "/problems/<slug>", never
 * a bare "<slug>". Set the base to an absolute URI (your docs) to meet the recommendation fully.
 */
final readonly class BaseUriTypeResolver implements TypeResolver
{
    public const string DEFAULT_BASE = '/problems';

    public function __construct(
        private string $baseUri = self::DEFAULT_BASE,
    ) {}

    public function resolve(string $type): string
    {
        if ($this->isAbsolute($type) || str_starts_with($type, '/')) {
            return $type;
        }

        return rtrim($this->baseUri, '/').'/'.$type;
    }

    /**
     * An RFC 3986 absolute URI starts with a scheme: about:blank, https://..., tag:..., urn:...
     */
    private function isAbsolute(string $type): bool
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $type) === 1;
    }
}
