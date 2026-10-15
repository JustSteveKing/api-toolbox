<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tools;

use RuntimeException;

/**
 * Builds src/Support/ApiGuide.php from the apiguide.dev error catalogue.
 *
 * The catalogue is a directory of Markdown files, one per error, whose front matter carries the
 * title and status code. The class constants and titles come from there. Which status maps to
 * which entry is a judgement, not data, so that list is kept here and only checked against the
 * catalogue: a mapped entry that disappears is an error, and a status that newly has exactly one
 * entry is reported for a person to decide on.
 */
final class ApiGuideGenerator
{
    public const string BASE = 'https://apiguide.dev/errors/';

    /**
     * Statuses whose plain meaning is one catalogue entry. 400 and 422 are left out on purpose:
     * each has several entries, and choosing one would claim more than the status says.
     */
    public const array STATUSES = [
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'resource-not-found',
        405 => 'method-not-allowed',
        406 => 'not-acceptable',
        408 => 'request-timeout',
        409 => 'resource-conflict',
        410 => 'gone',
        411 => 'length-required',
        412 => 'precondition-failed',
        413 => 'payload-too-large',
        414 => 'uri-too-long',
        415 => 'unsupported-media-type',
        416 => 'range-not-satisfiable',
        428 => 'precondition-required',
        429 => 'rate-limit-exceeded',
        431 => 'request-header-fields-too-large',
        500 => 'internal-server-error',
        501 => 'not-implemented',
        502 => 'bad-gateway',
        503 => 'service-unavailable',
        504 => 'gateway-timeout',
    ];

    /**
     * Statuses deliberately left without a mapping, so they are not reported as candidates.
     */
    public const array UNMAPPED = [
        // Several entries: malformed-request-body, invalid-query-parameter, and others.
        400,
        // RFC 9110 section 15.5.3: "reserved for future use", so it has no plain meaning to map,
        // even though insufficient-funds is the only entry that uses it.
        402,
        // Several entries: validation-failed, unprocessable-query, idempotency-key-conflict.
        422,
    ];

    /**
     * Reads the front matter the catalogue's content schema defines (src/content.config.ts in
     * apiguide.dev): title, statusCode and an optional deprecated flag.
     *
     * @return array{title: string, status: int, deprecated: bool}
     */
    public static function parseEntry(string $slug, string $markdown): array
    {
        if (preg_match('/\A---\R(.*?)\R---/s', $markdown, $match) !== 1) {
            throw new RuntimeException("[{$slug}] has no front matter.");
        }

        $fields = [];

        foreach (preg_split('/\R/', $match[1]) ?: [] as $line) {
            if (preg_match('/^([A-Za-z]+):\s*(.*?)\s*$/', $line, $field) === 1) {
                $fields[$field[1]] = trim($field[2], '"\'');
            }
        }

        if (($fields['title'] ?? '') === '' || ! ctype_digit($fields['statusCode'] ?? '')) {
            throw new RuntimeException("[{$slug}] needs a title and a statusCode in its front matter.");
        }

        return [
            'title' => $fields['title'],
            'status' => (int) $fields['statusCode'],
            'deprecated' => ($fields['deprecated'] ?? '') === 'true',
        ];
    }

    /**
     * Statuses that now have exactly one entry but no mapping, for a person to look at.
     *
     * @param  array<string, array{title: string, status: int, deprecated: bool}>  $entries
     * @return array<int, string>
     */
    public static function unmappedCandidates(array $entries): array
    {
        $byStatus = [];

        foreach ($entries as $slug => $entry) {
            if (! $entry['deprecated']) {
                $byStatus[$entry['status']][] = $slug;
            }
        }

        $candidates = [];

        foreach ($byStatus as $status => $slugs) {
            if (count($slugs) === 1 && ! array_key_exists($status, self::STATUSES) && ! in_array($status, self::UNMAPPED, true)) {
                $candidates[$status] = $slugs[0];
            }
        }

        ksort($candidates);

        return $candidates;
    }

    /**
     * The class source, already in the form Pint leaves alone, so a regenerated file can be
     * compared byte for byte.
     *
     * @param  array<string, array{title: string, status: int, deprecated: bool}>  $entries
     */
    public static function render(array $entries): string
    {
        ksort($entries);

        foreach (self::STATUSES as $status => $slug) {
            if (! isset($entries[$slug])) {
                throw new RuntimeException("Status {$status} is mapped to [{$slug}], which the catalogue no longer has.");
            }

            if ($entries[$slug]['status'] !== $status) {
                throw new RuntimeException("Status {$status} is mapped to [{$slug}], which the catalogue gives status {$entries[$slug]['status']}.");
            }
        }

        $constants = [];
        $titles = [];
        $codes = [];

        foreach ($entries as $slug => $entry) {
            if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
                throw new RuntimeException("[{$slug}] is not a slug this generator can name a constant after.");
            }

            $name = self::constant($slug);
            $doc = $entry['deprecated']
                ? "    /**\n     * {$entry['status']}\n     *\n     * @deprecated Deprecated in the apiguide.dev catalogue.\n     */"
                : "    /** {$entry['status']} */";

            $constants[] = "{$doc}\n    public const string {$name} = self::BASE.'{$slug}';\n";
            $titles[] = "        self::{$name} => ".var_export($entry['title'], true).',';
            $codes[] = "        self::{$name} => {$entry['status']},";
        }

        $statuses = [];

        foreach (self::STATUSES as $status => $slug) {
            $statuses[] = "        {$status} => self::".self::constant($slug).',';
        }

        $base = self::BASE;

        $template = <<<PHP
            <?php

            declare(strict_types=1);

            namespace JustSteveKing\\Toolbox\\Support;

            /**
             * The error catalogue at apiguide.dev, as problem type URIs (RFC 9457, section 3.1.1).
             *
             * Each URI resolves to a page documenting the problem: when to use it, its status and an example,
             * which is what section 3.1.1 asks a type URI to dereference to. Use them in your own problem
             * types as well:
             *
             *     #[ProblemType(ApiGuide::ACCOUNT_FROZEN, title: 'Account Frozen', status: 403)]
             *
             * Generated by scripts/generate-apiguide.php from the catalogue's main branch, with every URI
             * checked to resolve. Do not edit it by hand: run `composer apiguide`.
             *
             * @see https://apiguide.dev/errors
             */
            final class ApiGuide
            {
                public const string BASE = '{$base}';

            %CONSTANTS%
                /**
                 * Each type's title, as the catalogue gives it.
                 */
                private const array TITLES = [
            %TITLES%
                ];

                /**
                 * Each type's status code, as the catalogue gives it.
                 */
                private const array STATUS_CODES = [
            %CODES%
                ];

                /**
                 * The catalogue entry for a bare status code, where one entry is the plain meaning of that
                 * code. Codes with several candidates, 400 and 422 among them, have none: picking one would
                 * claim more than the status says.
                 */
                private const array STATUSES = [
            %STATUSES%
                ];

                public static function title(string \$type): ?string
                {
                    return self::TITLES[\$type] ?? null;
                }

                /**
                 * The status code the catalogue documents a type with.
                 */
                public static function status(string \$type): ?int
                {
                    return self::STATUS_CODES[\$type] ?? null;
                }

                public static function forStatus(int \$status): ?string
                {
                    return self::STATUSES[\$status] ?? null;
                }

                /**
                 * Every type URI in the catalogue.
                 *
                 * @return list<string>
                 */
                public static function types(): array
                {
                    return array_keys(self::TITLES);
                }
            }

            PHP;

        return str_replace(
            ['%CONSTANTS%', '%TITLES%', '%CODES%', '%STATUSES%'],
            [implode("\n", $constants), implode("\n", $titles), implode("\n", $codes), implode("\n", $statuses)],
            $template,
        );
    }

    private static function constant(string $slug): string
    {
        return strtoupper(str_replace('-', '_', $slug));
    }
}
