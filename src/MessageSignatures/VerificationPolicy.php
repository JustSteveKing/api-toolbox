<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

/**
 * What this application requires of a signature beyond the RFC itself, which section 3.2.1 of
 * RFC 9421 expects every application to define and enforce.
 */
final readonly class VerificationPolicy
{
    /**
     * @param  list<string>  $required  Components every accepted signature has to cover.
     * @param  int  $maxAge  Seconds a signature's created time may be in the past.
     * @param  int  $leeway  Seconds a created time may be in the future, for clock skew.
     * @param  bool  $coverContent  Require content-digest on a request with content, and check it.
     * @param  string|null  $tag  A tag every accepted signature has to carry.
     * @param  bool  $requireNonce  Require a nonce. This does not remember nonces; use a short
     *                              max age, or check uniqueness in the application.
     */
    public function __construct(
        public array $required = ['@method', '@authority', '@path'],
        public int $maxAge = 300,
        public int $leeway = 30,
        public bool $coverContent = true,
        public ?string $tag = null,
        public bool $requireNonce = false,
    ) {}
}
