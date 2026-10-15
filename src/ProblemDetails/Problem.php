<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use InvalidArgumentException;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

/**
 * An RFC 9457 problem details object.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9457#section-3
 */
final readonly class Problem implements JsonSerializable
{
    public const string ABOUT_BLANK = 'about:blank';

    public const string MEDIA_TYPE = 'application/problem+json';

    private const array MEMBERS = ['type', 'title', 'status', 'detail', 'instance'];

    /**
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public int $status,
        public string $type = self::ABOUT_BLANK,
        public ?string $title = null,
        public ?string $detail = null,
        public ?string $instance = null,
        public array $extensions = [],
    ) {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException("A problem needs an error status (4xx or 5xx), got [{$status}].");
        }

        // Section 4 says extension names SHOULD start with a letter, use only ALPHA, DIGIT and
        // "_", and be three or more characters. Those are SHOULDs, so they are not enforced. A
        // name that collides with a standard member is a different matter: it would silently
        // replace that member, so it is rejected.
        foreach (array_keys($extensions) as $name) {
            if (! is_string($name) || in_array($name, self::MEMBERS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Extension member [%s] must be a string that is not one of: %s (RFC 9457, section 3.1).',
                    $name,
                    implode(', ', self::MEMBERS),
                ));
            }
        }
    }

    /**
     * A problem with no semantics beyond its status code.
     *
     * Section 4.2.1: with "about:blank", the title SHOULD be the status code's recommended phrase.
     *
     * @param  array<string, mixed>  $extensions
     */
    public static function forStatus(int $status, ?string $detail = null, array $extensions = []): self
    {
        return new self(
            status: $status,
            title: Response::$statusTexts[$status] ?? null,
            detail: $detail,
            extensions: $extensions,
        );
    }

    /**
     * The members in the order the RFC lists them, then extensions. Null members are left out.
     *
     * "type" is always written, even as "about:blank". Section 3.1.1 makes it the default when
     * absent, but writing it means a client never has to know that rule.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'instance' => $this->instance,
        ], static fn (mixed $value): bool => $value !== null), $this->extensions);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
