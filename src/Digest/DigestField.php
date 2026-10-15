<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest;

use JustSteveKing\Toolbox\Support\DigestAlgorithm;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use JustSteveKing\Toolbox\Support\StructuredFields\StructuredFieldException;

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
    /**
     * The members, or null when the field is not a Dictionary (RFC 9651, section 4.2.2).
     *
     * @param  list<string>  $fieldValues
     * @return array<string, Item|InnerList>|null
     */
    public static function parse(array $fieldValues): ?array
    {
        try {
            return Parser::dictionary($fieldValues);
        } catch (StructuredFieldException) {
            return null;
        }
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
            $member = $members[$algorithm->value] ?? null;
            $weight = $member instanceof Item && is_int($member->value) ? $member->value : 0;

            if ($weight > $bestWeight && $weight <= 10) {
                $best = $algorithm;
                $bestWeight = $weight;
            }
        }

        return $best;
    }
}
