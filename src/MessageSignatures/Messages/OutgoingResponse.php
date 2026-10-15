<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Messages;

use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use LogicException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A response the application is about to send, with the request it answers.
 */
final readonly class OutgoingResponse implements Message
{
    public function __construct(
        private Response $response,
        private Request $request,
    ) {}

    public function isRequest(): bool
    {
        return false;
    }

    public function method(): string
    {
        throw new LogicException('A response has no method; use req to sign the request\'s.');
    }

    public function scheme(): string
    {
        throw new LogicException('A response has no scheme; use req to sign the request\'s.');
    }

    public function authority(): string
    {
        throw new LogicException('A response has no authority; use req to sign the request\'s.');
    }

    public function path(): string
    {
        throw new LogicException('A response has no path; use req to sign the request\'s.');
    }

    public function query(): ?string
    {
        throw new LogicException('A response has no query; use req to sign the request\'s.');
    }

    public function requestTarget(): string
    {
        throw new LogicException('A response has no request target; use req to sign the request\'s.');
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function field(string $name): ?array
    {
        if (! $this->response->headers->has($name)) {
            return null;
        }

        return array_values(array_map(strval(...), array_filter($this->response->headers->all($name), static fn (mixed $value): bool => $value !== null)));
    }

    public function request(): Message
    {
        return new IncomingRequest($this->request);
    }
}
