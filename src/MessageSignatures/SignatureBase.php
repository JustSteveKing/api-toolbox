<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\SignatureBaseException;
use JustSteveKing\Toolbox\Support\StructuredFields\ByteSequence;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use JustSteveKing\Toolbox\Support\StructuredFields\Serializer;
use JustSteveKing\Toolbox\Support\StructuredFields\StructuredFieldException;
use LogicException;

/**
 * Builds the signature base (RFC 9421, section 2.5): one line per covered component, then the
 * signature parameters.
 *
 *     "@method": POST
 *     "@authority": example.com
 *     "@signature-params": ("@method" "@authority");created=1618884473;keyid="test-key-ed25519"
 *
 * Trailer fields (the tr parameter, section 2.1.4) are not supported: PHP does not see a
 * request's trailers, so a component naming one is an error, as section 2.5 requires for a value
 * that cannot be obtained.
 */
final class SignatureBase
{
    /**
     * Fields this knows the Structured Field type of, for the sf and key parameters (sections
     * 2.1.1 and 2.1.2). Section 2.5 says sf on a field of unknown type is an error.
     */
    private const array STRUCTURED = [
        'accept-signature' => 'dictionary',
        'cache-status' => 'list',
        'content-digest' => 'dictionary',
        'deprecation' => 'item',
        'idempotency-key' => 'item',
        'priority' => 'dictionary',
        'proxy-status' => 'list',
        'ratelimit' => 'list',
        'ratelimit-policy' => 'list',
        'repr-digest' => 'dictionary',
        'signature' => 'dictionary',
        'signature-input' => 'dictionary',
        'want-content-digest' => 'dictionary',
        'want-repr-digest' => 'dictionary',
    ];

    private const array DERIVED = ['@method', '@target-uri', '@authority', '@scheme', '@request-target', '@path', '@query', '@query-param', '@status'];

    /**
     * @param  InnerList  $parameters  The covered components, as Strings with their parameters,
     *                                 carrying the signature parameters.
     */
    public static function create(Message $message, InnerList $parameters): string
    {
        $lines = [];
        $seen = [];

        foreach ($parameters->items as $component) {
            if (! is_string($component->value)) {
                throw new SignatureBaseException('A component identifier is a String.');
            }

            // Step 2.1: each identifier once. Parameter order does not matter for equality.
            $identity = $component->value.'|'.self::sorted($component->parameters);

            if (isset($seen[$identity])) {
                throw new SignatureBaseException("{$component->value} is covered more than once.");
            }

            $seen[$identity] = true;

            $lines[] = Serializer::item($component).': '.self::value($message, $component);
        }

        $lines[] = '"@signature-params": '.Serializer::innerList($parameters);
        $base = implode("\n", $lines);

        // Step 4.
        if (preg_match('/[^\x00-\x7F]/', $base) === 1) {
            throw new SignatureBaseException('A signature base is ASCII.');
        }

        return $base;
    }

    private static function value(Message $message, Item $component): string
    {
        $name = $component->value;

        if (! is_string($name)) {
            throw new SignatureBaseException('A component identifier is a String.');
        }

        $parameters = $component->parameters;
        $allowed = str_starts_with($name, '@')
            ? ($name === '@query-param' ? ['req', 'name'] : ['req'])
            : ['sf', 'key', 'bs', 'req', 'tr'];

        foreach (array_keys($parameters) as $parameter) {
            if (! in_array($parameter, $allowed, true)) {
                throw new SignatureBaseException("{$name} does not take the {$parameter} parameter.");
            }
        }

        if ($name === '@signature-params') {
            throw new SignatureBaseException('@signature-params cannot be a covered component (section 2.3).');
        }

        // Section 2.4: req takes the value from the request a response answers.
        if (($parameters['req'] ?? false) === true) {
            if ($message->isRequest()) {
                throw new SignatureBaseException('req cannot be used when signing a request (section 2.4).');
            }

            $message = $message->request() ?? throw new SignatureBaseException('There is no request for req to refer to.');
        }

        $value = str_starts_with($name, '@')
            ? self::derived($message, $name, $parameters)
            : self::field($message, $name, $parameters);

        if (str_contains($value, "\n")) {
            throw new SignatureBaseException("The value of {$name} contains a newline.");
        }

        return $value;
    }

    /**
     * Section 2.2.
     *
     * @param  array<string, mixed>  $parameters
     */
    private static function derived(Message $message, string $name, array $parameters): string
    {
        if (! in_array($name, self::DERIVED, true)) {
            throw new SignatureBaseException("{$name} is not a derived component this knows.");
        }

        if ($name === '@status') {
            if ($message->isRequest()) {
                throw new SignatureBaseException('@status cannot be used on a request (section 2.2.9).');
            }

            return (string) $message->status();
        }

        if (! $message->isRequest()) {
            throw new SignatureBaseException("{$name} refers to a request; use req to sign it on a response.");
        }

        try {
            return match ($name) {
                '@method' => $message->method(),
                '@target-uri' => $message->scheme().'://'.$message->authority().$message->path().($message->query() === null ? '' : '?'.$message->query()),
                '@authority' => $message->authority(),
                '@scheme' => $message->scheme(),
                '@request-target' => $message->requestTarget(),
                '@path' => $message->path(),
                // Section 2.2.7: an absent query is "?" alone.
                '@query' => '?'.($message->query() ?? ''),
                default => self::queryParameter($message, $parameters['name'] ?? null),
            };
        } catch (LogicException $e) {
            throw new SignatureBaseException($e->getMessage(), previous: $e);
        }
    }

    /**
     * Section 2.2.8. Names and values are parsed as application/x-www-form-urlencoded, then
     * encoded with the URL Standard's "percent-encode after encoding" and that form's
     * percent-encode set. That process's spaceAsPlus defaults to false, so a space is %20, which
     * is what the section's own examples show.
     */
    private static function queryParameter(Message $message, mixed $name): string
    {
        if (! is_string($name)) {
            throw new SignatureBaseException('@query-param needs a name parameter, a String.');
        }

        $matches = [];

        foreach (explode('&', $message->query() ?? '') as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            if (self::encode(self::decode($key)) === $name) {
                $matches[] = self::encode(self::decode($value));
            }
        }

        // "If a parameter name occurs multiple times in a request, the named query parameter
        // MUST NOT be included."
        return match (count($matches)) {
            1 => $matches[0],
            0 => throw new SignatureBaseException("The query has no {$name} parameter."),
            default => throw new SignatureBaseException("The query has {$name} more than once, so it cannot be signed by name."),
        };
    }

    private static function decode(string $value): string
    {
        return rawurldecode(str_replace('+', ' ', $value));
    }

    private static function encode(string $value): string
    {
        return (string) preg_replace_callback(
            '/[^A-Za-z0-9*\-._]/',
            static fn (array $match): string => sprintf('%%%02X', ord($match[0])),
            $value,
        );
    }

    /**
     * Section 2.1.
     *
     * @param  array<string, mixed>  $parameters
     */
    private static function field(Message $message, string $name, array $parameters): string
    {
        if ($name !== strtolower($name)) {
            throw new SignatureBaseException("Field component names are lowercase; got {$name}.");
        }

        if (($parameters['tr'] ?? false) === true) {
            throw new SignatureBaseException("{$name};tr refers to a trailer field, which is not available here.");
        }

        $values = $message->field($name) ?? throw new SignatureBaseException("The message has no {$name} field.");

        // Steps 2 and 3 of the section 2.1 algorithm: trim each line and unfold obsolete folding.
        $values = array_map(static fn (string $value): string => trim((string) preg_replace('/\r?\n[ \t]+/', ' ', $value), " \t"), $values);

        $binary = ($parameters['bs'] ?? false) === true;
        $structured = ($parameters['sf'] ?? false) === true || array_key_exists('key', $parameters);

        if ($binary && $structured) {
            throw new SignatureBaseException('bs cannot be combined with sf or key (section 2.1).');
        }

        // Section 2.1.3.
        if ($binary) {
            return Serializer::list(array_map(static fn (string $value): Item => new Item(new ByteSequence($value)), $values));
        }

        if (! $structured) {
            return implode(', ', $values);
        }

        try {
            if (array_key_exists('key', $parameters)) {
                return self::dictionaryMember($name, $values, $parameters['key']);
            }

            // Section 2.1.1.
            return match (self::STRUCTURED[$name] ?? null) {
                'dictionary' => Serializer::dictionary(Parser::dictionary($values)),
                'list' => Serializer::list(Parser::list($values)),
                'item' => Serializer::item(Parser::item($values)),
                default => throw new SignatureBaseException("{$name} is not a Structured Field this knows the type of."),
            };
        } catch (StructuredFieldException $e) {
            throw new SignatureBaseException("{$name} is not a valid Structured Field: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Section 2.1.2.
     *
     * @param  list<string>  $values
     */
    private static function dictionaryMember(string $name, array $values, mixed $key): string
    {
        if (! is_string($key)) {
            throw new SignatureBaseException('The key parameter is a String.');
        }

        if ((self::STRUCTURED[$name] ?? 'dictionary') !== 'dictionary') {
            throw new SignatureBaseException("{$name} is not a Dictionary.");
        }

        $dictionary = Parser::dictionary($values);

        if (! array_key_exists($key, $dictionary)) {
            throw new SignatureBaseException("{$name} has no {$key} member.");
        }

        return Serializer::member($dictionary[$key]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function sorted(array $parameters): string
    {
        ksort($parameters);

        return serialize($parameters);
    }
}
