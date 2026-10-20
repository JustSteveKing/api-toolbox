<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency;

use Illuminate\Cache\NullStore;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use JsonException;
use LogicException;

/**
 * Completed responses and in-flight locks, in any Laravel cache store that supports locks.
 *
 * A stored response can hold a secret, such as an API key shown once when it is created, and a
 * replay has to return it. So each response is encrypted with a key derived from the application
 * key and the client's own Idempotency-Key, and stored under a hash of that key. The cache alone
 * cannot be read, and nor can the cache with the application key: decrypting an entry takes the
 * Idempotency-Key that created it, which only the client has.
 */
final readonly class IdempotencyStore
{
    private const string PREFIX = 'api-toolbox:idempotency:';

    private LockProvider $locks;

    /**
     * @param  int  $ttl  Seconds a completed response is kept, and so how long a key stays valid.
     * @param  int  $lockSeconds  Longest a request is treated as in flight, should it never finish.
     * @param  list<string>  $keys  The application's encryption keys, current first, then any previous ones.
     */
    public function __construct(
        private Repository $cache,
        private int $ttl,
        private int $lockSeconds,
        private array $keys,
    ) {
        if ($keys === []) {
            throw new LogicException('Idempotency needs the application key to encrypt stored responses.');
        }

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
     * An entry that cannot be decrypted is treated as missing. That happens when the application
     * key changes and the old one is not kept in APP_PREVIOUS_KEYS.
     *
     * @phpstan-impure
     */
    public function get(string $scope, IdempotencyKey $key): ?StoredResponse
    {
        $entry = $this->cache->get(self::PREFIX.self::id($scope, $key));

        // Versions before 0.4 stored the response unencrypted. Reading those until they expire
        // keeps a retry that straddles an upgrade from running twice.
        if (is_array($entry)) {
            return StoredResponse::fromArray($entry);
        }

        if (! is_string($entry)) {
            return null;
        }

        foreach ($this->keys as $applicationKey) {
            try {
                $json = self::encrypter($applicationKey, $scope, $key)->decryptString($entry);

                return StoredResponse::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
            } catch (DecryptException|JsonException) {
                continue;
            }
        }

        return null;
    }

    public function put(string $scope, IdempotencyKey $key, StoredResponse $response): void
    {
        $json = json_encode($response->toArray(), JSON_THROW_ON_ERROR);

        $this->cache->put(
            self::PREFIX.self::id($scope, $key),
            self::encrypter($this->keys[0], $scope, $key)->encryptString($json),
            $this->ttl,
        );
    }

    public function lock(string $scope, IdempotencyKey $key): Lock
    {
        return $this->locks->lock(self::PREFIX.'lock:'.self::id($scope, $key), $this->lockSeconds);
    }

    /**
     * Where an entry is kept: a hash, so the cache never holds the Idempotency-Key itself.
     */
    private static function id(string $scope, IdempotencyKey $key): string
    {
        return hash('sha256', $scope."\n".$key->value);
    }

    /**
     * HKDF (RFC 5869) from the application key, with the scope and Idempotency-Key as the
     * context, so every entry has its own key and none can be derived without the client's.
     */
    private static function encrypter(string $applicationKey, string $scope, IdempotencyKey $key): Encrypter
    {
        $derived = hash_hkdf('sha256', $applicationKey, 32, 'api-toolbox idempotency v1|'.$scope."\n".$key->value);

        return new Encrypter($derived, 'aes-256-gcm');
    }

    public function ttl(): int
    {
        return $this->ttl;
    }
}
