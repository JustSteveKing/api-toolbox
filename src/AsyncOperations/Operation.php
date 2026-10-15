<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

use DateTimeImmutable;

/**
 * One asynchronous operation, as its status monitor reports it.
 */
final readonly class Operation
{
    /**
     * @param  array<string, mixed>|null  $error  A problem object describing why it failed.
     */
    public function __construct(
        public string $id,
        public OperationStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public ?string $owner = null,
        public ?string $location = null,
        public ?array $error = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $error
     */
    public function with(OperationStatus $status, ?string $location = null, ?array $error = null): self
    {
        return new self($this->id, $status, $this->createdAt, new DateTimeImmutable, $this->owner, $location ?? $this->location, $error);
    }

    /**
     * The representation the 202 and the status monitor send.
     *
     * @return array<string, mixed>
     */
    public function toRepresentation(): array
    {
        return array_filter([
            'id' => $this->id,
            'status' => $this->status->value,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'location' => $this->location,
            'error' => $this->error,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...$this->toRepresentation(), 'owner' => $this->owner];
    }

    public static function fromArray(mixed $data): ?self
    {
        if (! is_array($data)
            || ! is_string($data['id'] ?? null)
            || ! is_string($data['status'] ?? null)
            || ($status = OperationStatus::tryFrom($data['status'])) === null
            || ! is_string($data['created_at'] ?? null)
            || ! is_string($data['updated_at'] ?? null)) {
            return null;
        }

        $error = $data['error'] ?? null;

        return new self(
            $data['id'],
            $status,
            new DateTimeImmutable($data['created_at']),
            new DateTimeImmutable($data['updated_at']),
            is_string($data['owner'] ?? null) ? $data['owner'] : null,
            is_string($data['location'] ?? null) ? $data['location'] : null,
            is_array($error) ? self::stringKeys($error) : null,
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    private static function stringKeys(array $values): array
    {
        $keyed = [];

        foreach ($values as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }
}
