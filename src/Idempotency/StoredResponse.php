<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency;

use Symfony\Component\HttpFoundation\Response;

/**
 * A completed response, kept so a retry can be answered with it (draft section 2.6), together
 * with the fingerprint of the request that produced it.
 */
final readonly class StoredResponse
{
    /**
     * @param  array<string, list<string|null>>  $headers
     */
    public function __construct(
        public string $fingerprint,
        public int $status,
        public array $headers,
        public string $content,
    ) {}

    public static function capture(string $fingerprint, Response $response): ?self
    {
        $content = $response->getContent();

        // Streamed and file responses have no content to keep.
        if (! is_string($content)) {
            return null;
        }

        $headers = self::headers($response->headers->allPreserveCaseWithoutCookies());

        // Date describes when a message was sent; a replay is sent now.
        unset($headers['Date']);

        return new self($fingerprint, $response->getStatusCode(), $headers, $content);
    }

    public function toResponse(): Response
    {
        return new Response($this->content, $this->status, $this->headers);
    }

    /**
     * @return array{fingerprint: string, status: int, headers: array<string, list<string|null>>, content: string}
     */
    public function toArray(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'status' => $this->status,
            'headers' => $this->headers,
            'content' => $this->content,
        ];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data)
            || ! is_string($data['fingerprint'] ?? null)
            || ! is_int($data['status'] ?? null)
            || ! is_array($data['headers'] ?? null)
            || ! is_string($data['content'] ?? null)) {
            return null;
        }

        return new self($data['fingerprint'], $data['status'], self::headers($data['headers']), $data['content']);
    }

    /**
     * @param  array<mixed>  $headers
     * @return array<string, list<string|null>>
     */
    private static function headers(array $headers): array
    {
        $normalised = [];

        foreach ($headers as $name => $values) {
            $normalised[(string) $name] = array_values(array_filter(is_array($values) ? $values : [], is_string(...)));
        }

        return $normalised;
    }
}
