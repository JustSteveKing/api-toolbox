<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Preferences;

use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Contracts\ClientPreferences;

/**
 * The Prefer request field (RFC 7240, section 2):
 *
 *     Prefer     = 1#preference
 *     preference = token [ BWS "=" BWS word ] *( OWS ";" [ OWS parameter ] )
 *     parameter  = token [ BWS "=" BWS word ]
 *
 * Preference and parameter names compare case-insensitively, values case-sensitively. An empty
 * value is no value. Only the first occurrence of a preference counts. The mutually exclusive
 * pairs, return=minimal with return=representation and handling=strict with handling=lenient,
 * cancel each other out, as sections 4.2 and 4.4 suggest.
 */
final class Preferences implements ClientPreferences
{
    /**
     * Where honoured preferences are kept on the request, for Preference-Applied.
     */
    public const string APPLIED = '_api_toolbox_preferences_applied';

    private const string TOKEN = "[!#$%&'*+\\-.^_`|~0-9A-Za-z]+";

    /**
     * @param  array<string, array{value: string|null, parameters: array<string, string|null>}>  $preferences
     */
    private function __construct(
        private readonly array $preferences,
        private readonly ?Request $request = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(self::parse(array_values(array_filter($request->headers->all('prefer'), is_string(...)))), $request);
    }

    /**
     * @param  list<string>  $fieldValues
     */
    public static function fromHeader(array $fieldValues): self
    {
        return new self(self::parse($fieldValues));
    }

    public function prefers(string $preference, ?string $value = null): bool
    {
        $preference = strtolower($preference);

        if (! array_key_exists($preference, $this->preferences)) {
            return false;
        }

        return $value === null || $this->preferences[$preference]['value'] === $value;
    }

    public function value(string $preference): ?string
    {
        return $this->preferences[strtolower($preference)]['value'] ?? null;
    }

    /**
     * @return array<string, string|null>
     */
    public function parameters(string $preference): array
    {
        return $this->preferences[strtolower($preference)]['parameters'] ?? [];
    }

    public function returnMinimal(): bool
    {
        return $this->prefers('return', 'minimal');
    }

    public function returnRepresentation(): bool
    {
        return $this->prefers('return', 'representation');
    }

    public function respondAsync(): bool
    {
        return $this->prefers('respond-async');
    }

    public function handlingStrict(): bool
    {
        return $this->prefers('handling', 'strict');
    }

    public function handlingLenient(): bool
    {
        return $this->prefers('handling', 'lenient');
    }

    /**
     * Section 4.3: wait = "wait" BWS "=" BWS delta-seconds. Null when absent or not a number.
     */
    public function wait(): ?int
    {
        $value = $this->value('wait');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    public function applied(string $preference, ?string $value = null): void
    {
        if ($this->request === null) {
            return;
        }

        $applied = $this->request->attributes->get(self::APPLIED, []);
        $applied = is_array($applied) ? $applied : [];
        $applied[strtolower($preference)] = $value;

        $this->request->attributes->set(self::APPLIED, $applied);
    }

    /**
     * @param  list<string>  $fieldValues
     * @return array<string, array{value: string|null, parameters: array<string, string|null>}>
     */
    private static function parse(array $fieldValues): array
    {
        $preferences = [];

        // Section 2: several Prefer fields are one comma-separated list.
        foreach (self::split(implode(',', $fieldValues), ',') as $member) {
            $parts = self::split($member, ';');
            [$name, $value] = self::pair(array_shift($parts) ?? '');

            // Section 2: only the first occurrence of a preference is considered.
            if ($name === null || array_key_exists($name, $preferences)) {
                continue;
            }

            $parameters = [];

            foreach ($parts as $part) {
                [$parameter, $parameterValue] = self::pair($part);

                if ($parameter !== null && ! array_key_exists($parameter, $parameters)) {
                    $parameters[$parameter] = $parameterValue;
                }
            }

            $preferences[$name] = ['value' => $value, 'parameters' => $parameters];
        }

        foreach (['return' => ['minimal', 'representation'], 'handling' => ['strict', 'lenient']] as $name => $exclusive) {
            // Section 2 keeps only the first occurrence of a preference, which on its own would keep
            // return=minimal from "return=minimal, return=representation". Sections 4.2 and 4.4 say
            // a request with both can be "treated as though neither were specified". The more
            // specific rule is applied: both values in one request mean a client bug, and guessing
            // which one it meant is worse than honouring neither.
            $values = [];

            foreach (self::split(implode(',', $fieldValues), ',') as $member) {
                [$memberName, $memberValue] = self::pair(self::split($member, ';')[0]);

                if ($memberName === $name && in_array($memberValue, $exclusive, true)) {
                    $values[$memberValue] = true;
                }
            }

            if (count($values) > 1) {
                unset($preferences[$name]);
            }
        }

        return $preferences;
    }

    /**
     * token [ BWS "=" BWS word ], where word = token / quoted-string. An empty value is no value.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private static function pair(string $text): array
    {
        if (preg_match('/^\s*('.self::TOKEN.')\s*(?:=\s*(?:('.self::TOKEN.')|"((?:[^"\\\\]|\\\\.)*)")?)?\s*$/', $text, $match) !== 1) {
            return [null, null];
        }

        $value = isset($match[3]) ? (string) preg_replace('/\\\\(.)/', '$1', $match[3]) : ($match[2] ?? '');

        return [strtolower($match[1]), $value === '' ? null : $value];
    }

    /**
     * @return list<string>
     */
    private static function split(string $value, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $quoted = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($quoted && $char === '\\' && $i + 1 < $length) {
                $current .= $char.$value[++$i];

                continue;
            }

            if ($char === '"') {
                $quoted = ! $quoted;
            }

            if ($char === $delimiter && ! $quoted) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }
}
