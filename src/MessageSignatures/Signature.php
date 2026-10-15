<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use JustSteveKing\Toolbox\Support\StructuredFields\ByteSequence;
use JustSteveKing\Toolbox\Support\StructuredFields\InnerList;
use JustSteveKing\Toolbox\Support\StructuredFields\Item;
use JustSteveKing\Toolbox\Support\StructuredFields\Parser;
use JustSteveKing\Toolbox\Support\StructuredFields\Serializer;
use JustSteveKing\Toolbox\Support\StructuredFields\StructuredFieldException;

/**
 * A signature and its parameters, ready to go into Signature-Input and Signature (RFC 9421,
 * section 4), both Dictionaries keyed by the signature's label.
 */
final readonly class Signature
{
    public function __construct(
        public string $label,
        public InnerList $parameters,
        public string $bytes,
    ) {}

    /**
     * The two field values, with this signature added to any the message already carries.
     * Section 4.3: a message can carry several signatures, each under its own label.
     *
     * @param  list<string>  $existingInput
     * @param  list<string>  $existingSignature
     * @return array{Signature-Input: string, Signature: string}
     */
    public function fields(array $existingInput = [], array $existingSignature = []): array
    {
        try {
            $inputs = $existingInput === [] ? [] : Parser::dictionary($existingInput);
            $signatures = $existingSignature === [] ? [] : Parser::dictionary($existingSignature);
        } catch (StructuredFieldException) {
            $inputs = [];
            $signatures = [];
        }

        $inputs[$this->label] = $this->parameters;
        $signatures[$this->label] = new Item(new ByteSequence($this->bytes));

        return [
            'Signature-Input' => Serializer::dictionary($inputs),
            'Signature' => Serializer::dictionary($signatures),
        ];
    }
}
