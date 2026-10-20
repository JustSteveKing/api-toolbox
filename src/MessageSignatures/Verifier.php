<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use Illuminate\Support\Carbon;
use JustSteveKing\Toolbox\MessageSignatures\Algorithms\Algorithms;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\MalformedSignature;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\SignatureBaseException;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\SignatureVerificationFailed;
use JustSteveKing\Toolbox\Support\DigestAlgorithm;
use JustSteveKing\Toolbox\Support\Messages;
use JustSteveKing\Toolbox\Support\StructuredFields\ByteSequence;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use JustSteveKing\Toolbox\Support\StructuredFields\Serializer;
use JustSteveKing\Toolbox\Support\StructuredFields\StructuredFieldException;

/**
 * Verifies a message's signature (RFC 9421, section 3.2) against this application's policy
 * (section 3.2.1).
 *
 * Every signature on the message is a candidate, matched on its key and what it covers, never on
 * its label: labels are the signer's choice, and intermediaries may add signatures of their own
 * (section 4.3). The first candidate that passes every check is accepted.
 *
 * Checks, in order: a known key, the algorithm, coverage, the signature itself, the content
 * digest, then the age.
 */
final readonly class Verifier
{
    public function __construct(
        private KeyResolver $keys,
        private VerificationPolicy $policy = new VerificationPolicy,
    ) {}

    /**
     * @param  string  $content  The message content, to check Content-Digest against.
     */
    public function verify(Message $message, string $content = ''): VerifiedSignature
    {
        [$inputs, $signatures] = $this->fields($message);

        if ($inputs === []) {
            throw $this->fail(self::reason('not_signed'), $content);
        }

        $reason = self::reason('unknown_key');

        foreach ($inputs as $label => $parameters) {
            $signature = $signatures[$label] ?? null;

            // Section 3.2, step 1.2.
            if (! $parameters instanceof InnerList || ! $signature instanceof Item || ! $signature->value instanceof ByteSequence) {
                continue;
            }

            $keyId = $parameters->parameters['keyid'] ?? null;
            $key = is_string($keyId) ? $this->keys->resolve($keyId) : null;

            // Section 3.2, step 5: an unknown or untrusted key MUST fail. It fails this candidate.
            if ($key === null) {
                continue;
            }

            $failure = $this->check($message, $content, $parameters, $signature->value->bytes, $key);

            if ($failure === null) {
                return new VerifiedSignature(
                    (string) $label,
                    $key->id,
                    is_int($parameters->parameters['created'] ?? null) ? $parameters->parameters['created'] : null,
                    array_map(static fn (Item $item): string => Serializer::item($item), $parameters->items),
                );
            }

            $reason = $failure;
        }

        throw $this->fail($reason, $content);
    }

    /**
     * The reason the candidate fails, or null when it passes.
     */
    private function check(Message $message, string $content, InnerList $parameters, string $bytes, Key $key): ?string
    {
        // Section 3.2, step 6.5: an alg that disagrees with the key's algorithm MUST fail.
        $alg = $parameters->parameters['alg'] ?? null;

        if ($alg !== null && $alg !== $key->algorithm) {
            return self::reason('algorithm_mismatch');
        }

        $covered = array_map(Components::key(...), $parameters->items);

        foreach ($this->required($content) as $component) {
            if (! in_array(Components::key($component), $covered, true)) {
                return self::reason('not_covered', ['component' => $component]);
            }
        }

        if ($this->policy->tag !== null && ($parameters->parameters['tag'] ?? null) !== $this->policy->tag) {
            return self::reason('missing_tag');
        }

        if ($this->policy->requireNonce && ! is_string($parameters->parameters['nonce'] ?? null)) {
            return self::reason('missing_nonce');
        }

        try {
            $base = SignatureBase::create($message, $parameters);
            $valid = Algorithms::named($key->algorithm)->verify($base, $bytes, $key);
        } catch (SignatureBaseException|KeyException) {
            return self::reason('unresolvable_component');
        }

        if (! $valid) {
            return self::reason('mismatch');
        }

        // A signature over content-digest proves only that the field was not changed. The digest
        // has to match the content actually received too, or the content is not covered at all.
        if (in_array(Components::key('content-digest'), $covered, true) && ! $this->digestMatches($message, $content)) {
            return self::reason('content_digest_mismatch');
        }

        return $this->age($parameters);
    }

    private function age(InnerList $parameters): ?string
    {
        $now = Carbon::now()->getTimestamp();
        $created = $parameters->parameters['created'] ?? null;
        $expires = $parameters->parameters['expires'] ?? null;

        if (! is_int($created)) {
            return self::reason('missing_created');
        }

        if ($created > $now + $this->policy->leeway) {
            return self::reason('created_in_future');
        }

        if ($now - $created > $this->policy->maxAge) {
            return self::reason('too_old');
        }

        if (is_int($expires) && $now > $expires) {
            return self::reason('expired');
        }

        return null;
    }

    private function digestMatches(Message $message, string $content): bool
    {
        try {
            $digests = Parser::dictionary($message->field('content-digest') ?? []);
        } catch (StructuredFieldException) {
            return false;
        }

        $checked = 0;

        foreach ($digests as $name => $digest) {
            $algorithm = DigestAlgorithm::tryFrom((string) $name);

            if ($algorithm === null) {
                continue;
            }

            if (! $digest instanceof Item || ! $digest->value instanceof ByteSequence || ! hash_equals($algorithm->digest($content), $digest->value->bytes)) {
                return false;
            }

            $checked++;
        }

        return $checked > 0;
    }

    /**
     * @return list<string>
     */
    private function required(string $content): array
    {
        $required = $this->policy->required;

        if ($this->policy->coverContent && $content !== '' && ! in_array('content-digest', $required, true)) {
            $required[] = 'content-digest';
        }

        return $required;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function fields(Message $message): array
    {
        try {
            return [
                Parser::dictionary($message->field('signature-input') ?? []),
                Parser::dictionary($message->field('signature') ?? []),
            ];
        } catch (StructuredFieldException $e) {
            throw new MalformedSignature(Messages::get('message_signatures.malformed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Section 5.1: in a response, Accept-Signature asks the client to sign its next request with
     * these components.
     */
    /**
     * @param  array<string, string>  $replace
     */
    private static function reason(string $key, array $replace = []): string
    {
        return Messages::get('message_signatures.reasons.'.$key, $replace);
    }

    private function fail(string $reason, string $content): SignatureVerificationFailed
    {
        $parameters = ['created' => true];

        if ($this->policy->tag !== null) {
            $parameters['tag'] = $this->policy->tag;
        }

        // A nonce is not asked for here: in Accept-Signature, nonce carries the value the signer
        // is to use, and the server has none to give.
        $accept = Serializer::dictionary(['sig1' => new InnerList(array_map(Components::item(...), $this->required($content)), $parameters)]);

        return new SignatureVerificationFailed($reason, $accept);
    }
}
