<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use JustSteveKing\Toolbox\Support\StructuredFields\Serializer;

/**
 * Component identifiers, written either as a bare name ("@method", "content-digest") or in their
 * serialized form with parameters ('"@query-param";name="Pet"', '"@path";req').
 */
final class Components
{
    public static function item(string|Item $component): Item
    {
        if ($component instanceof Item) {
            return $component;
        }

        return str_starts_with($component, '"') ? Parser::item($component) : new Item($component);
    }

    /**
     * The identifier's serialized form, with parameters sorted, for comparing two identifiers:
     * parameter order does not make them different (RFC 9421, section 2).
     */
    public static function key(string|Item $component): string
    {
        $item = self::item($component);
        $parameters = $item->parameters;
        ksort($parameters);

        return Serializer::item(new Item($item->value, $parameters));
    }
}
