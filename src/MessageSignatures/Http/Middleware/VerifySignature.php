<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\MessageSignatures\Messages\IncomingRequest;
use JustSteveKing\Toolbox\MessageSignatures\VerifiedSignature;
use JustSteveKing\Toolbox\MessageSignatures\Verifier;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Requires a valid HTTP message signature on the request (RFC 9421), as the feature's verify
 * policy defines it.
 *
 *     ->middleware('signature.verify')
 *
 * The accepted signature is kept on the request: VerifiedSignature::of($request)->keyId says who
 * signed it.
 */
final readonly class VerifySignature
{
    public function __construct(
        private Verifier $verifier,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $signature = $this->verifier->verify(new IncomingRequest($request), $request->getContent());

        $request->attributes->set(VerifiedSignature::ATTRIBUTE, $signature);

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }
}
