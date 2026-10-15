<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Messages;

use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use LogicException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A request as the application received it.
 */
final readonly class IncomingRequest implements Message
{
    public function __construct(
        private Request $request,
    ) {}

    public function isRequest(): bool
    {
        return true;
    }

    public function method(): string
    {
        return $this->request->getMethod();
    }

    public function scheme(): string
    {
        return strtolower($this->request->getScheme());
    }

    /**
     * getHttpHost() leaves out the scheme's default port, as section 4.2.3 of RFC 9110 asks.
     */
    public function authority(): string
    {
        return strtolower($this->request->getHttpHost());
    }

    public function path(): string
    {
        $path = explode('?', $this->request->getRequestUri(), 2)[0];

        return $path === '' ? '/' : $path;
    }

    public function query(): ?string
    {
        $parts = explode('?', $this->request->getRequestUri(), 2);

        return $parts[1] ?? null;
    }

    public function requestTarget(): string
    {
        return $this->request->getRequestUri();
    }

    public function status(): int
    {
        throw new LogicException('A request has no status.');
    }

    public function field(string $name): ?array
    {
        if (! $this->request->headers->has($name)) {
            return null;
        }

        return array_values(array_map(strval(...), array_filter($this->request->headers->all($name), static fn (mixed $value): bool => $value !== null)));
    }

    public function request(): ?Message
    {
        return null;
    }
}
