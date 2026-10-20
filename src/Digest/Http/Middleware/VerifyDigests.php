<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Digest\DigestField;
use JustSteveKing\Toolbox\Digest\Exceptions\DigestProblem;
use JustSteveKing\Toolbox\Support\DigestAlgorithm;
use JustSteveKing\Toolbox\Support\Messages;
use JustSteveKing\Toolbox\Support\StructuredFields\ByteSequence;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Serializer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use UnexpectedValueException;

/**
 * Checks Content-Digest and Repr-Digest on a request against its content (RFC 9530).
 *
 *     ->middleware('digest.verify')            checks digests when sent
 *     ->middleware('digest.verify:required')   400 without one
 *
 * For a request body, the content and the representation data are the same bytes, so both fields
 * are checked against the raw body as received. Each supported algorithm is computed at most once
 * per field, which bounds the work section 6.7 warns about.
 *
 * A field with no supported algorithm at all is refused; members with algorithms this does not
 * support are otherwise ignored, as section 2 allows, so a client can send a new algorithm beside
 * one the server knows.
 */
final readonly class VerifyDigests
{
    private const array FIELDS = ['Content-Digest', 'Repr-Digest'];

    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $present = array_values(array_filter(self::FIELDS, static fn (string $field): bool => $request->headers->has($field)));

        if ($present === [] && in_array('required', $options, true)) {
            throw new BadRequestHttpException(Messages::get('digest.missing'), headers: [
                'Want-Content-Digest' => 'sha-256=10, sha-512=10',
            ]);
        }

        foreach ($present as $field) {
            $this->verify($field, $request);
        }

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }

    private function verify(string $field, Request $request): void
    {
        $members = DigestField::parse(array_values(array_filter($request->headers->all(strtolower($field)), is_string(...))));

        // The draft's invalid-values type is for values that parse but cannot be digests, so a
        // field that is not a Dictionary at all is a plain 400.
        if ($members === null) {
            throw new BadRequestHttpException(Messages::get('digest.not_a_dictionary', ['field' => $field]));
        }

        $content = $request->getContent();
        $unsupported = [];
        $invalid = [];
        $mismatched = [];
        $verified = 0;

        foreach ($members as $key => $value) {
            $algorithm = DigestAlgorithm::tryFrom((string) $key);

            if ($algorithm === null) {
                $unsupported[] = ['algorithm' => $key, 'header' => $field];

                continue;
            }

            $bytes = $value instanceof Item && $value->value instanceof ByteSequence ? $value->value->bytes : null;

            if ($bytes === null || strlen($bytes) !== $algorithm->length()) {
                $invalid[] = [
                    'algorithm' => $key,
                    'header' => $field,
                    'reason' => $bytes === null
                        ? 'digest value is not a Byte Sequence'
                        : "digest value is not {$algorithm->length()} bytes long",
                ];

                continue;
            }

            if (! hash_equals($algorithm->digest($content), $bytes)) {
                $mismatched[] = ['algorithm' => $key, 'provided_digest' => Serializer::member($value), 'header' => $field];

                continue;
            }

            $verified++;
        }

        // Any supported digest that is wrong fails the request: section 6.7 leaves which to check
        // to the application, and accepting a request with one bad digest out of two is a weakness.
        if ($invalid !== []) {
            throw DigestProblem::invalidValues($invalid);
        }

        if ($mismatched !== []) {
            throw DigestProblem::mismatchedValues($mismatched);
        }

        if ($verified === 0) {
            throw DigestProblem::unsupportedAlgorithms($unsupported);
        }
    }
}
