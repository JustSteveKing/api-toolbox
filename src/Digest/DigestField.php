<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest;

/**
 * Content-Digest and Repr-Digest (RFC 9530, sections 2 and 3) are Dictionaries whose keys name a
 * hashing algorithm and whose values are Byte Sequences:
 *
 *     Content-Digest: sha-256=:d435Qo+nKZ+gLcUHn7GQtQ72hiBVAgqoLsZnZPiTGPk=:, sha-512=:YMAa...==:
 *
 * The Want- fields (section 4) are Dictionaries of Integers from 0 to 10.
 */
final class DigestField
{
    private const string KEY = '[a-z*][a-z0-9_\-.*]*';

    /**
     * Each member's raw value, or null when the field is not a Dictionary (RFC 9651, section
     * 4.2.2). Parameters on members are allowed and ignored.
     *
     * @param  list<string>  $fieldValues
     * @return array<string, string>|null
     */
    public static function parse(array $fieldValues): ?array
    {
        $field = trim(implode(', ', $fieldValues), ' ');
        $members = [];

        while ($field !== '') {
            if (preg_match('/^('.self::KEY.')(?:=(:[A-Za-z0-9+\/=]*:|-?\d{1,15}|"(?:[^"\\\\]|\\\\.)*"|\?[01]|[A-Za-z*][!#$%&\'*+\-.^_`|~0-9A-Za-z:\/]*))?((?:;[ ]*'.self::KEY.'(?:=[^,;]*)?)*)\s*/', $field, $match) !== 1) {
                return null;
            }

            // RFC 9651 section 4.2.2: a later duplicate key overwrites an earlier one.
            // A key with no value is a Boolean true (RFC 9651, section 4.2.2).
            $members[$match[1]] = $match[2] !== '' ? $match[2] : '?1';
            $field = substr($field, strlen($match[0]));

            if ($field === '') {
                break;
            }

            if ($field[0] !== ',') {
                return null;
            }

            $field = ltrim(substr($field, 1), " \t");

            if ($field === '') {
                return null;
            }
        }

        return $members;
    }

    /**
     * The decoded bytes of a Byte Sequence value, or null when the value is not one.
     */
    public static function bytes(string $value): ?string
    {
        if (preg_match('/^:([A-Za-z0-9+\/=]*):$/', $value, $match) !== 1) {
            return null;
        }

        $decoded = base64_decode($match[1], true);

        return $decoded === false ? null : $decoded;
    }

    public static function serialize(DigestAlgorithm $algorithm, string $content): string
    {
        return $algorithm->value.'=:'.base64_encode($algorithm->digest($content)).':';
    }

    /**
     * The algorithm a Want- field prefers most among those supported, or null when it wants none
     * of them. Section 4: weights run 1 (least) to 10 (most), and 0 means not acceptable. A tie
     * goes to SHA-512.
     *
     * @param  list<string>  $fieldValues
     */
    public static function preferred(array $fieldValues): ?DigestAlgorithm
    {
        $members = self::parse($fieldValues) ?? [];
        $best = null;
        $bestWeight = 0;

        foreach ([DigestAlgorithm::Sha512, DigestAlgorithm::Sha256] as $algorithm) {
            $weight = $members[$algorithm->value] ?? null;

            if ($weight !== null && ctype_digit($weight) && (int) $weight > $bestWeight && (int) $weight <= 10) {
                $best = $algorithm;
                $bestWeight = (int) $weight;
            }
        }

        return $best;
    }
}
