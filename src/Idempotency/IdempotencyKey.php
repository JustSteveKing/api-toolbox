<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency;

/**
 * The value of an Idempotency-Key request header.
 *
 * draft-ietf-httpapi-idempotency-key-header-07, section 2.1, makes it a Structured Field String:
 *
 *     Idempotency-Key: "8e03978e-40d5-43e8-bc93-6894a57f9324"
 *
 * Widely used clients (Stripe's among them) send the key unquoted, so a bare value is accepted
 * too. Everything this package writes or documents uses the quoted form.
 */
final readonly class IdempotencyKey
{
    /**
     * Long enough for any UUID or ULID, which section 2.2 recommends, short enough to bound what
     * a client can make the server hash and store.
     */
    public const int MAX_LENGTH = 255;

    private function __construct(
        public string $value,
    ) {}

    public static function parse(string $field): ?self
    {
        $field = trim($field, " \t");

        // RFC 9651, section 3.3.3: sf-string = DQUOTE *( unescaped / "%" / bs-escaped ) DQUOTE,
        // where unescaped is printable ASCII other than DQUOTE and backslash.
        if (preg_match('/^"((?:[\x20-\x21\x23-\x5B\x5D-\x7E]|\\\\["\\\\])*)"$/', $field, $match) === 1) {
            $value = (string) preg_replace('/\\\\(["\\\\])/', '$1', $match[1]);
        }

        // The bare form: visible ASCII, without the quote and backslash that would make it
        // ambiguous with the string form.
        elseif (preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/', $field) === 1) {
            $value = $field;
        } else {
            return null;
        }

        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        return new self($value);
    }

    /**
     * The quoted form from section 2.1.
     */
    public function toHeaderValue(): string
    {
        return '"'.addcslashes($this->value, '"\\').'"';
    }
}
