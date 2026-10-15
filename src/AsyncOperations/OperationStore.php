<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

/**
 * Operation records in a Laravel cache store, kept for a TTL after their last change.
 *
 * A cache can evict before the TTL under memory pressure (Redis or Memcached with an eviction
 * policy); the status monitor then answers 404, as it does for an operation that never existed.
 */
final readonly class OperationStore
{
    private const string PREFIX = 'api-toolbox:operation:';

    public function __construct(
        private Repository $cache,
        private int $ttl,
    ) {}

    public function create(?string $owner): Operation
    {
        $now = new DateTimeImmutable;

        $operation = new Operation((string) Str::uuid7(), OperationStatus::Pending, $now, $now, $owner);

        $this->save($operation);

        return $operation;
    }

    /**
     * @phpstan-impure
     */
    public function find(string $id): ?Operation
    {
        return Operation::fromArray($this->cache->get(self::PREFIX.$id));
    }

    public function save(Operation $operation): void
    {
        $this->cache->put(self::PREFIX.$operation->id, $operation->toArray(), $this->ttl);
    }
}
