<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency;

use Illuminate\Cache\NullStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use LogicException;

/**
 * Completed responses and in-flight locks, in any Laravel cache store that supports locks.
 */
final readonly class IdempotencyStore
{
    private const string PREFIX = 'api-toolbox:idempotency:';

    private LockProvider $locks;

    /**
     * @param  int  $ttl  Seconds a completed response is kept, and so how long a key stays valid.
     * @param  int  $lockSeconds  Longest a request is treated as in flight, should it never finish.
     */
    public function __construct(
        private Repository $cache,
        private int $ttl,
        private int $lockSeconds,
    ) {
        $store = $cache->getStore();

        // Without an atomic lock there is no way to tell a retry from a concurrent duplicate,
        // which is the case section 2.6 answers with 409.
        if (! $store instanceof LockProvider) {
            throw new LogicException(sprintf(
                'Idempotency needs a cache store that supports atomic locks; %s does not.',
                $store::class,
            ));
        }

        // The null store "locks" and "stores" by doing nothing, so every retry would run again.
        if ($store instanceof NullStore) {
            throw new LogicException('Idempotency cannot use the null cache store: it keeps nothing, so no retry would ever be recognised.');
        }

        $this->locks = $store;
    }

    /**
     * Reads shared state another request can change at any moment.
     *
     * @phpstan-impure
     */
    public function get(string $key): ?StoredResponse
    {
        return StoredResponse::fromArray($this->cache->get(self::PREFIX.$key));
    }

    public function put(string $key, StoredResponse $response): void
    {
        $this->cache->put(self::PREFIX.$key, $response->toArray(), $this->ttl);
    }

    public function lock(string $key): Lock
    {
        return $this->locks->lock(self::PREFIX.'lock:'.$key, $this->lockSeconds);
    }

    public function ttl(): int
    {
        return $this->ttl;
    }
}
