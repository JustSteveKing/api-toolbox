<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MergePatch;

use JsonException;
use JustSteveKing\Toolbox\MergePatch\Exceptions\MalformedMergePatch;
use stdClass;

/**
 * A JSON Merge Patch document (RFC 7396).
 *
 * The patch is decoded with JSON objects kept as objects. That matters: RFC 7396 treats the
 * patch {} as "change nothing" and the patch [] as "replace the whole target with an empty
 * array", and PHP's associative decoding turns both into [].
 */
final readonly class MergePatch
{
    public const string MEDIA_TYPE = 'application/merge-patch+json';

    private function __construct(
        private mixed $patch,
    ) {}

    public static function fromJson(string $json): self
    {
        try {
            return new self(json_decode($json, false, 512, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new MalformedMergePatch($e->getMessage());
        }
    }

    /**
     * Section 2, exactly:
     *
     *     define MergePatch(Target, Patch):
     *       if Patch is an Object:
     *         if Target is not an Object:
     *           Target = {}
     *         for each Name/Value pair in Patch:
     *           if Value is null:
     *             if Name exists in Target:
     *               remove the Name/Value pair from Target
     *           else:
     *             Target[Name] = MergePatch(Target[Name], Value)
     *         return Target
     *       else:
     *         return Patch
     *
     * $target is a decoded JSON value. A PHP array that is a list is a JSON array; any other array
     * is a JSON object.
     */
    public function apply(mixed $target): mixed
    {
        return self::merge(self::toJson($target), $this->patch);
    }

    /**
     * Applies the patch to a document whose top level is an object, such as a model's attributes,
     * and returns the result as a PHP array.
     *
     * The result is an array unless the patch replaced the whole document with something else.
     *
     * @param  array<string, mixed>  $document
     */
    public function applyTo(array $document): mixed
    {
        return self::toArray(self::merge(self::object($document), $this->patch));
    }

    /**
     * The top-level members the patch changes, as attributes ready for Model::update(): changed
     * values as they are after the patch, and removed members as null.
     *
     * Only a patch whose top level is an object describes changes to members; any other patch
     * replaces the whole document, which has no member-by-member form.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function changesTo(array $document): array
    {
        if (! $this->patch instanceof stdClass) {
            throw new MalformedMergePatch('A patch that replaces the whole document cannot be applied member by member. Send an object.');
        }

        $result = $this->applyTo($document);
        $result = is_array($result) ? $result : [];
        $changes = [];

        foreach (array_keys(get_object_vars($this->patch)) as $name) {
            $changes[(string) $name] = array_key_exists($name, $result) ? $result[$name] : null;
        }

        return $changes;
    }

    /**
     * Whether the patch's top level is an object, the only shape that patches rather than
     * replaces.
     */
    public function isObject(): bool
    {
        return $this->patch instanceof stdClass;
    }

    private static function merge(mixed $target, mixed $patch): mixed
    {
        if (! $patch instanceof stdClass) {
            return $patch;
        }

        $target = $target instanceof stdClass ? clone $target : new stdClass;

        foreach (get_object_vars($patch) as $name => $value) {
            if ($value === null) {
                unset($target->{$name});
            } else {
                $target->{$name} = self::merge($target->{$name} ?? null, $value);
            }
        }

        return $target;
    }

    /**
     * A PHP value as the JSON value it stands for: lists become arrays, other arrays objects.
     */
    private static function toJson(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_is_list($value) && $value !== []
            ? array_map(self::toJson(...), $value)
            : self::object($value);
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function object(array $values): stdClass
    {
        $object = new stdClass;

        foreach ($values as $name => $value) {
            $object->{(string) $name} = self::toJson($value);
        }

        return $object;
    }

    /**
     * A decoded JSON value back into PHP arrays.
     */
    private static function toArray(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            return array_map(self::toArray(...), get_object_vars($value));
        }

        return is_array($value) ? array_map(self::toArray(...), $value) : $value;
    }
}
