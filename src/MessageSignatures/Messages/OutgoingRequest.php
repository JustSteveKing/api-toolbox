<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Messages;

use JustSteveKing\Toolbox\MessageSignatures\Contracts\Message;
use LogicException;
use Psr\Http\Message\RequestInterface;

/**
 * A request the HTTP client is about to send.
 */
final readonly class OutgoingRequest implements Message
{
    private const array DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function __construct(
        private RequestInterface $request,
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
        return strtolower($this->request->getUri()->getScheme());
    }

    public function authority(): string
    {
        $uri = $this->request->getUri();
        $port = $uri->getPort();
        $host = strtolower($uri->getHost());

        return $port === null || $port === (self::DEFAULT_PORTS[$this->scheme()] ?? null) ? $host : "{$host}:{$port}";
    }

    public function path(): string
    {
        $path = $this->request->getUri()->getPath();

        return $path === '' ? '/' : $path;
    }

    public function query(): ?string
    {
        $query = $this->request->getUri()->getQuery();

        return $query === '' ? null : $query;
    }

    public function requestTarget(): string
    {
        return $this->request->getRequestTarget();
    }

    public function status(): int
    {
        throw new LogicException('A request has no status.');
    }

    public function field(string $name): ?array
    {
        return $this->request->hasHeader($name) ? array_values($this->request->getHeader($name)) : null;
    }

    public function request(): ?Message
    {
        return null;
    }
}
