<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Testing\Parsers;

use InvalidArgumentException;

/**
 * Parses Link field values as RFC 8288, section 3 defines them:
 *
 *     Link       = #link-value
 *     link-value = "<" URI-Reference ">" *( OWS ";" OWS link-param )
 *     link-param = token BWS [ "=" BWS ( token / quoted-string ) ]
 *
 * Strict on purpose: it exists to tell a test whether a header is well formed, so anything it
 * cannot parse is an error rather than something to skip.
 */
final class LinkHeader
{
    private const string TOKEN = "[!#$%&'*+\\-.^_`|~0-9A-Za-z]+";

    /**
     * @param  list<string>  $fieldValues  One entry per Link field line in the response.
     * @return list<array{uri: string, params: array<string, string|null>}>
     */
    public static function parse(array $fieldValues): array
    {
        $links = [];

        foreach ($fieldValues as $value) {
            $rest = trim($value);

            while ($rest !== '') {
                if (preg_match('/^<([^<>\s]*)>/', $rest, $uri) !== 1) {
                    throw new InvalidArgumentException("Expected <URI-Reference> at [{$rest}] (RFC 8288, section 3).");
                }

                $rest = substr($rest, strlen($uri[0]));
                $params = [];

                while (preg_match('/^[ \t]*;[ \t]*('.self::TOKEN.')[ \t]*(?:=[ \t]*(?:('.self::TOKEN.')|"((?:[^"\\\\]|\\\\.)*)"))?/', $rest, $param) === 1) {
                    // Section 3: parameter names are case-insensitive; rel, rev, media, title,
                    // title* and type each occur at most once, and later ones are ignored.
                    $name = strtolower($param[1]);

                    if (! array_key_exists($name, $params)) {
                        $params[$name] = match (true) {
                            // RFC 9110 section 5.6.4: a quoted-pair is a backslash and the character it escapes.
                            isset($param[3]) => (string) preg_replace('/\\\\(.)/s', '$1', $param[3]),
                            isset($param[2]) && $param[2] !== '' => $param[2],
                            default => null,
                        };
                    }

                    $rest = substr($rest, strlen($param[0]));
                }

                $links[] = ['uri' => $uri[1], 'params' => $params];

                $rest = ltrim($rest, " \t");

                if ($rest === '') {
                    break;
                }

                if ($rest[0] !== ',') {
                    throw new InvalidArgumentException("Expected \",\" or the end of the field at [{$rest}] (RFC 8288, section 3).");
                }

                $rest = ltrim(substr($rest, 1), " \t");
            }
        }

        return $links;
    }

    /**
     * Links whose rel includes $rel. Section 3.3: rel can hold several space-separated types, and
     * registered types compare case-insensitively.
     *
     * @param  list<string>  $fieldValues
     * @return list<array{uri: string, params: array<string, string|null>}>
     */
    public static function withRel(array $fieldValues, string $rel): array
    {
        return array_values(array_filter(
            self::parse($fieldValues),
            static fn (array $link): bool => in_array(
                strtolower($rel),
                explode(' ', strtolower((string) ($link['params']['rel'] ?? ''))),
                true,
            ),
        ));
    }
}
