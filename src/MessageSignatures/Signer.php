<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use Illuminate\Support\Carbon;
use JustSteveKing\Toolbox\MessageSignatures\Algorithms\Algorithms;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use JustSteveKing\Toolbox\MessageSignatures\Exceptions\KeyException;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;

/**
 * Creates a signature (RFC 9421, section 3.1).
 *
 * created is always set, as section 3.1 says signers SHOULD. keyid is always set, so a verifier
 * can find the key. alg is only set when asked for: the verifier should know the algorithm from
 * the key, and section 7.3.6 warns against letting the message choose it.
 */
final readonly class Signer
{
    public function __construct(
        private KeyResolver $keys,
    ) {}

    /**
     * @param  list<string|Item>  $components  Names such as "@method" or "content-digest", or an
     *                                         Item for one with parameters.
     * @param  array{expires?: int, nonce?: string, tag?: string, alg?: bool}  $options
     */
    public function sign(Message $message, string $keyId, array $components, string $label = 'sig1', array $options = []): Signature
    {
        $key = $this->keys->resolve($keyId) ?? throw new KeyException("No key [{$keyId}] is configured.");

        $created = Carbon::now()->getTimestamp();

        $parameters = ['created' => $created];

        if (isset($options['expires'])) {
            $parameters['expires'] = $options['expires'];
        }

        $parameters['keyid'] = $keyId;

        if (($options['alg'] ?? false) === true) {
            $parameters['alg'] = $key->algorithm;
        }

        if (isset($options['nonce'])) {
            $parameters['nonce'] = $options['nonce'];
        }

        if (isset($options['tag'])) {
            $parameters['tag'] = $options['tag'];
        }

        $covered = new InnerList(
            array_map(Components::item(...), $components),
            $parameters,
        );

        $base = SignatureBase::create($message, $covered);

        return new Signature($label, $covered, Algorithms::named($key->algorithm)->sign($base, $key));
    }
}
