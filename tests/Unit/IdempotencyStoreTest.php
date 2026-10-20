<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use JustSteveKing\Toolbox\Idempotency\IdempotencyKey;
use JustSteveKing\Toolbox\Idempotency\IdempotencyStore;
use JustSteveKing\Toolbox\Idempotency\StoredResponse;

it('refuses a cache store that cannot lock, rather than fail open', function (): void {
    $store = new class implements Store
    {
        public function get($key): mixed
        {
            return null;
        }

        public function many(array $keys): array
        {
            return [];
        }

        public function put($key, $value, $seconds): bool
        {
            return true;
        }

        public function putMany(array $values, $seconds): bool
        {
            return true;
        }

        public function increment($key, $value = 1): int|bool
        {
            return 1;
        }

        public function decrement($key, $value = 1): int|bool
        {
            return 1;
        }

        public function forever($key, $value): bool
        {
            return true;
        }

        public function touch($key, $seconds): bool
        {
            return true;
        }

        public function forget($key): bool
        {
            return true;
        }

        public function flush(): bool
        {
            return true;
        }

        public function getPrefix(): string
        {
            return '';
        }
    };

    new IdempotencyStore(new Repository($store), 60, 60, ['key']);
})->throws(LogicException::class, 'needs a cache store that supports atomic locks');

it('refuses the null store, which would recognise no retry', function (): void {
    new IdempotencyStore(new Repository(new Illuminate\Cache\NullStore), 60, 60, ['key']);
})->throws(LogicException::class, 'cannot use the null cache store');

function storedResponse(): StoredResponse
{
    return new StoredResponse('fingerprint', 201, ['Content-Type' => ['application/json']], '{"token":"secret-value"}');
}

function idempotencyKey(string $value): IdempotencyKey
{
    return IdempotencyKey::parse('"'.$value.'"') ?? throw new LogicException('Invalid key.');
}

it('refuses to run without an application key', function (): void {
    new IdempotencyStore(new Repository(new ArrayStore), 60, 60, []);
})->throws(LogicException::class, 'Idempotency needs the application key to encrypt stored responses.');

it('reads back what it stored', function (): void {
    $store = new IdempotencyStore(new Repository(new ArrayStore), 60, 60, ['app-key']);

    $store->put('user:1', idempotencyKey('abc'), storedResponse());

    expect($store->get('user:1', idempotencyKey('abc')))->toEqual(storedResponse());
});

it('keeps neither the response nor the key readable in the cache', function (): void {
    $cache = new Repository(new ArrayStore);
    $store = new IdempotencyStore($cache, 60, 60, ['app-key']);

    $store->put('user:1', idempotencyKey('abc'), storedResponse());

    $entry = $cache->get('api-toolbox:idempotency:'.hash('sha256', "user:1\nabc"));

    expect($entry)->toBeString()
        ->not->toContain('secret-value')
        ->not->toContain('abc');
});

it('cannot read an entry without the Idempotency-Key that wrote it, even with the application key', function (): void {
    $cache = new Repository(new ArrayStore);
    $store = new IdempotencyStore($cache, 60, 60, ['app-key']);

    $store->put('user:1', idempotencyKey('abc'), storedResponse());

    // Copy the entry to where another key's response would live.
    $cache->put(
        'api-toolbox:idempotency:'.hash('sha256', "user:1\nxyz"),
        $cache->get('api-toolbox:idempotency:'.hash('sha256', "user:1\nabc")),
        60,
    );

    expect($store->get('user:1', idempotencyKey('xyz')))->toBeNull();
});

it('reads entries written under a previous application key', function (): void {
    $cache = new Repository(new ArrayStore);

    (new IdempotencyStore($cache, 60, 60, ['old-key']))->put('user:1', idempotencyKey('abc'), storedResponse());

    expect((new IdempotencyStore($cache, 60, 60, ['new-key', 'old-key']))->get('user:1', idempotencyKey('abc')))
        ->toEqual(storedResponse())
        ->and((new IdempotencyStore($cache, 60, 60, ['new-key']))->get('user:1', idempotencyKey('abc')))
        ->toBeNull();
});

it('reads an unencrypted entry left by an earlier version', function (): void {
    $cache = new Repository(new ArrayStore);
    $cache->put('api-toolbox:idempotency:'.hash('sha256', "user:1\nabc"), storedResponse()->toArray(), 60);

    expect((new IdempotencyStore($cache, 60, 60, ['app-key']))->get('user:1', idempotencyKey('abc')))
        ->toEqual(storedResponse());
});
