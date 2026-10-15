<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\MessageSignatures\Messages\OutgoingResponse;
use JustSteveKing\Toolbox\MessageSignatures\Signer;
use JustSteveKing\Toolbox\Support\DigestAlgorithm;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Signs the response (RFC 9421) with a configured key.
 *
 *     ->middleware('signature.sign')            the feature's default signing key
 *     ->middleware('signature.sign:server-2026') a named key
 *
 * Covers @status, Content-Type when there is one, and Content-Digest, which is added when the
 * response has content and no digest yet: a signature over the headers alone says nothing about
 * the body. The request's method and path are covered too, with req (section 2.4), so a response
 * cannot be passed off as the answer to a different request.
 */
final readonly class SignResponse
{
    public function __construct(
        private Signer $signer,
        private ?string $defaultKey,
    ) {}

    public function handle(Request $request, Closure $next, ?string $keyId = null): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        $keyId ??= $this->defaultKey ?? throw new UnexpectedValueException('signature.sign needs a key: name one, or set the feature\'s sign.key option.');

        $content = $response->getContent();
        $components = ['@status'];

        if ($response->headers->has('Content-Type')) {
            $components[] = 'content-type';
        }

        if (is_string($content) && $content !== '') {
            if (! $response->headers->has('Content-Digest')) {
                $response->headers->set('Content-Digest', DigestAlgorithm::Sha256->value.'=:'.base64_encode(DigestAlgorithm::Sha256->digest($content)).':');
            }

            $components[] = 'content-digest';
        }

        $components[] = '"@method";req';
        $components[] = '"@path";req';

        $signature = $this->signer->sign(new OutgoingResponse($response, $request), $keyId, $components);

        foreach ($signature->fields(
            array_values(array_map(strval(...), array_filter($response->headers->all('signature-input'), static fn (mixed $value): bool => $value !== null))),
            array_values(array_map(strval(...), array_filter($response->headers->all('signature'), static fn (mixed $value): bool => $value !== null))),
        ) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
