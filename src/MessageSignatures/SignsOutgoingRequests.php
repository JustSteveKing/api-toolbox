<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use Illuminate\Container\Container;
use Illuminate\Http\Client\PendingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Messages\OutgoingRequest;
use JustSteveKing\Toolbox\Support\DigestAlgorithm;
use Psr\Http\Message\RequestInterface;

/**
 * Adds withSignature() to Laravel's HTTP client, which is how webhooks get signed:
 *
 *     Http::withSignature('webhooks')->post($endpoint, $payload);
 *
 * Covers @method, @target-uri, Content-Type when there is one, and Content-Digest, added for a
 * request with a body. The macro is registered from a static method, so the closure holds no
 * reference to the application that registered it.
 */
final class SignsOutgoingRequests
{
    public static function register(): void
    {
        PendingRequest::macro('withSignature', function (string $keyId, ?array $components = null, string $label = 'sig1', array $options = []): PendingRequest {
            return $this->withRequestMiddleware(static function (RequestInterface $request) use ($keyId, $components, $label, $options): RequestInterface {
                return SignsOutgoingRequests::sign($request, $keyId, $components, $label, $options);
            });
        });
    }

    /**
     * @param  array<mixed>|null  $components
     * @param  array<mixed>  $options
     */
    public static function sign(RequestInterface $request, string $keyId, ?array $components, string $label, array $options): RequestInterface
    {
        $components = $components === null ? null : array_values(array_filter($components, is_string(...)));
        $options = array_filter([
            'expires' => is_int($options['expires'] ?? null) ? $options['expires'] : null,
            'nonce' => is_string($options['nonce'] ?? null) ? $options['nonce'] : null,
            'tag' => is_string($options['tag'] ?? null) ? $options['tag'] : null,
            'alg' => ($options['alg'] ?? false) === true ? true : null,
        ], static fn (mixed $value): bool => $value !== null);

        $body = (string) $request->getBody();
        $request->getBody()->rewind();

        if ($body !== '' && ! $request->hasHeader('Content-Digest')) {
            $request = $request->withHeader('Content-Digest', DigestAlgorithm::Sha256->value.'=:'.base64_encode(DigestAlgorithm::Sha256->digest($body)).':');
        }

        if ($components === null) {
            $components = ['@method', '@target-uri'];

            if ($request->hasHeader('Content-Type')) {
                $components[] = 'content-type';
            }

            if ($body !== '') {
                $components[] = 'content-digest';
            }
        }

        $signer = Container::getInstance()->make(Signer::class);
        $signature = $signer->sign(new OutgoingRequest($request), $keyId, $components, $label, $options);

        foreach ($signature->fields(array_values($request->getHeader('Signature-Input')), array_values($request->getHeader('Signature'))) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}
