<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ApiCatalog;

use Illuminate\Contracts\Routing\UrlGenerator;
use InvalidArgumentException;
use JustSteveKing\Toolbox\ApiCatalog\Contracts\CatalogSource;

/**
 * Builds the API catalog document (RFC 9727, section 4) in the Linkset format RFC 9264 section 4.2
 * defines, combining the two shapes RFC 9727's Appendix A shows:
 *
 *  - the catalog's own entry, anchored at its URL, lists each API with "item" (Appendix A.2), which
 *    satisfies section 4.1's MUST: the catalog includes hyperlinks to the API endpoints;
 *  - each API then has an entry of its own, anchored at the API, carrying its RFC 8631 links
 *    (Appendix A.1), the metadata section 4.1 RECOMMENDS.
 *
 * Other catalogs are linked from the catalog's entry with "api-catalog" (section 4.3).
 */
final readonly class ApiCatalog
{
    public const string MEDIA_TYPE = 'application/linkset+json';

    public const string PROFILE = 'https://www.rfc-editor.org/info/rfc9727';

    /**
     * The RFC 8631 relations, in the order RFC 9727's examples use them.
     */
    public const array RELATIONS = ['service-desc', 'service-doc', 'service-meta', 'status'];

    public function __construct(
        private CatalogSource $source,
        private UrlGenerator $urls,
    ) {}

    /**
     * @return array{linkset: list<array<string, mixed>>}
     */
    public function document(string $catalogUrl): array
    {
        $apis = $this->source->apis();
        $catalog = ['anchor' => $catalogUrl];
        $entries = [];

        foreach ($apis as $index => $api) {
            $href = $api['href'] ?? null;

            if (! is_string($href) || $href === '') {
                throw new InvalidArgumentException("API catalog entry {$index} needs an href, the API's URL.");
            }

            $anchor = $this->absolute($href);
            $catalog['item'][] = ['href' => $anchor];

            $entry = ['anchor' => $anchor];

            foreach (self::RELATIONS as $relation) {
                if (array_key_exists($relation, $api)) {
                    $entry[$relation] = $this->targets($api[$relation]);
                }
            }

            if (count($entry) > 1) {
                $entries[] = $entry;
            }
        }

        foreach ($this->source->catalogs() as $nested) {
            $catalog['api-catalog'][] = ['href' => $this->absolute($nested)];
        }

        return ['linkset' => [$catalog, ...$entries]];
    }

    /**
     * RFC 9264 section 4.2.2: even a single target is wrapped in an array.
     *
     * @return list<array<string, mixed>>
     */
    private function targets(mixed $value): array
    {
        $targets = is_array($value) && array_is_list($value) ? $value : [$value];

        return array_map(function (mixed $target): array {
            if (is_string($target)) {
                return ['href' => $this->absolute($target)];
            }

            if (! is_array($target) || ! is_string($target['href'] ?? null)) {
                throw new InvalidArgumentException('An API catalog link target is a URL, or an array with an href.');
            }

            $object = ['href' => $this->absolute($target['href'])];

            // RFC 9264 section 4.2.4.1: hreflang is always an array; the others are strings.
            foreach (['type', 'title', 'media'] as $attribute) {
                if (is_string($target[$attribute] ?? null)) {
                    $object[$attribute] = $target[$attribute];
                }
            }

            if (isset($target['hreflang'])) {
                $object['hreflang'] = array_values(array_filter((array) $target['hreflang'], is_string(...)));
            }

            return $object;
        }, $targets);
    }

    /**
     * RFC 9264 sections 4.2.2 and 4.2.3: anchors and targets SHOULD NOT be relative references.
     */
    private function absolute(string $url): string
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1 ? $url : $this->urls->to($url);
    }
}
