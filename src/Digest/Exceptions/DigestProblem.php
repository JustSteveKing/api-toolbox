<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest\Exceptions;

use JustSteveKing\Toolbox\Contracts\HasProblemExtensions;
use JustSteveKing\Toolbox\Contracts\HasProblemType;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The three problems draft-ietf-httpapi-digest-fields-problem-types-06 defines, with the extension
 * members it defines for each, under apiguide.dev type URIs that mirror the draft's.
 *
 * The draft's own types (https://iana.org/assignments/http-problem-types#digest-...) are not yet
 * registered and do not resolve. Once they are, only the type URI needs to change: the extension
 * members already use the draft's names.
 */
final class DigestProblem extends BadRequestHttpException implements HasProblemExtensions, HasProblemType
{
    public const string UNSUPPORTED_ALGORITHMS = ApiGuide::DIGEST_UNSUPPORTED_ALGORITHMS;

    public const string INVALID_VALUES = ApiGuide::DIGEST_INVALID_VALUES;

    public const string MISMATCHED_VALUES = ApiGuide::DIGEST_MISMATCHED_VALUES;

    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    private function __construct(
        private readonly string $type,
        private readonly array $extensions,
        string $detail,
        array $headers = [],
    ) {
        parent::__construct($detail, headers: $headers);
    }

    /**
     * Draft section 3.1. The response carries the Want- fields saying what is supported, as the
     * draft's example does.
     *
     * @param  list<array{algorithm: string, header: string}>  $unsupported
     */
    public static function unsupportedAlgorithms(array $unsupported): self
    {
        return new self(
            self::UNSUPPORTED_ALGORITHMS,
            ['unsupported_algorithms' => $unsupported],
            'None of the hashing algorithms in the request is supported. Use sha-256 or sha-512.',
            [
                'Want-Content-Digest' => 'sha-256=10, sha-512=10',
                'Want-Repr-Digest' => 'sha-256=10, sha-512=10',
            ],
        );
    }

    /**
     * Draft section 3.2.
     *
     * @param  list<array{algorithm: string, header: string, reason: string}>  $invalid
     */
    public static function invalidValues(array $invalid): self
    {
        return new self(self::INVALID_VALUES, ['invalid_digests' => $invalid], 'A digest in the request cannot have come from its hashing algorithm.');
    }

    /**
     * Draft section 3.3. The server's own digest is deliberately left out, as the draft says, so
     * the response cannot be used as an oracle.
     *
     * @param  list<array{algorithm: string, provided_digest: string, header: string}>  $mismatched
     */
    public static function mismatchedValues(array $mismatched): self
    {
        return new self(self::MISMATCHED_VALUES, ['mismatched_digests' => $mismatched], 'A digest in the request does not match its content. It may have been changed in transit.');
    }

    public function problemType(): string
    {
        return $this->type;
    }

    public function problemTitle(): string
    {
        return ApiGuide::title($this->type) ?? 'Bad Request';
    }

    public function problemExtensions(): array
    {
        return $this->extensions;
    }
}
