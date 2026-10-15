<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 401 for a missing or failed signature, which apiguide.dev's Signature page recommends where the
 * signature stands in for authentication. RFC 9421 defines no status code.
 *
 * The detail names the check that failed but never anything that would help forge a signature.
 * Accept-Signature (RFC 9421, section 5.1) tells the client what to sign next time.
 */
final class SignatureVerificationFailed extends HttpException
{
    public function __construct(string $reason, string $acceptSignature)
    {
        parent::__construct(401, "The request's signature could not be verified: {$reason}", headers: [
            'Accept-Signature' => $acceptSignature,
        ]);
    }
}
