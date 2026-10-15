<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Contracts;

/**
 * The parts of an HTTP message RFC 9421 can sign, whatever object holds the message: an incoming
 * Laravel request, an outgoing Laravel response, or an outgoing PSR-7 request from the HTTP
 * client.
 */
interface Message
{
    public function isRequest(): bool;

    public function method(): string;

    public function scheme(): string;

    /**
     * Host and, when not the scheme's default, port, with the host lowercased (RFC 9110, section
     * 4.2.3).
     */
    public function authority(): string;

    /**
     * The path as sent, percent-encoding intact.
     */
    public function path(): string;

    /**
     * The query as sent, without the "?", or null when there is none.
     */
    public function query(): ?string;

    /**
     * The request target as on an HTTP/1.1 request line.
     */
    public function requestTarget(): string;

    public function status(): int;

    /**
     * Each line of the field, in order, or null when the message does not have it.
     *
     * @return list<string>|null
     */
    public function field(string $name): ?array;

    /**
     * The request a response answers, for components with the req parameter (RFC 9421, section
     * 2.4).
     */
    public function request(): ?self;
}
