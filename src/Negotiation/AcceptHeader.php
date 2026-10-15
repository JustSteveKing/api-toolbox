<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Negotiation;

/**
 * The Accept request field (RFC 9110, section 12.5.1):
 *
 *     Accept = #( media-range [ weight ] )
 *
 * Ranges that do not parse are ignored, so a client's typo narrows what it accepts rather than
 * failing the request. A field with no usable range is treated as absent, which section 12.5.1
 * says means any media type is acceptable.
 */
final readonly class AcceptHeader
{
    /**
     * @param  list<array{range: MediaType, quality: float}>  $ranges
     */
    private function __construct(
        private array $ranges,
    ) {}

    public static function parse(?string $field): self
    {
        $ranges = [];

        foreach (MediaType::split((string) $field, ',') as $member) {
            if (trim($member) === '') {
                continue;
            }

            [$range, $quality] = self::weight($member);

            if ($range !== null && $quality !== null) {
                $ranges[] = ['range' => $range, 'quality' => $quality];
            }
        }

        return new self($ranges);
    }

    /**
     * The quality a client gives $type: that of the most specific range covering it, or 0 when
     * none does. Section 12.4.3: without a wildcard, anything unlisted is unacceptable.
     */
    public function quality(MediaType $type): float
    {
        if ($this->ranges === []) {
            return 1.0;
        }

        $best = null;

        foreach ($this->ranges as $candidate) {
            if ($candidate['range']->covers($type)
                && ($best === null || $candidate['range']->specificity() > $best['range']->specificity())) {
                $best = $candidate;
            }
        }

        return $best['quality'] ?? 0.0;
    }

    /**
     * Section 12.4.2: "q" is a weight, not a media type parameter, whatever its position, and
     * qvalue = ( "0" [ "." 0*3DIGIT ] ) / ( "1" [ "." 0*3("0") ] ). An invalid weight discards
     * the range.
     *
     * @return array{0: MediaType|null, 1: float|null}
     */
    private static function weight(string $member): array
    {
        $quality = 1.0;
        $kept = [];

        foreach (MediaType::split($member, ';') as $index => $part) {
            if ($index > 0 && preg_match('/^\s*q\s*=\s*(.*?)\s*$/i', $part, $match) === 1) {
                if (preg_match('/^(0(\.\d{0,3})?|1(\.0{0,3})?)$/', $match[1]) !== 1) {
                    return [null, null];
                }

                $quality = (float) $match[1];

                continue;
            }

            $kept[] = $part;
        }

        return [MediaType::parse(implode(';', $kept)), $quality];
    }
}
