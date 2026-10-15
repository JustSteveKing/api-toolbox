<?php

declare(strict_types=1);

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use JustSteveKing\Toolbox\Idempotency\IdempotencyStore;

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

    new IdempotencyStore(new Repository($store), 60, 60);
})->throws(LogicException::class, 'needs a cache store that supports atomic locks');

it('refuses the null store, which would recognise no retry', function (): void {
    new IdempotencyStore(new Repository(new Illuminate\Cache\NullStore), 60, 60);
})->throws(LogicException::class, 'cannot use the null cache store');
