<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;

/**
 * Validators for an Eloquent model, from its key and updated_at.
 *
 * The entity tag is strong. Section 8.8.1 requires a strong tag to change whenever the
 * representation does, so this holds only while every change to the representation also touches
 * updated_at, and while updated_at is precise enough that two writes never share a value. MySQL's
 * default timestamp columns store whole seconds. For optimistic concurrency, override
 * entityTagVersion() to return a version column that every write increments.
 *
 * @phpstan-require-extends Model
 */
trait HasModelValidators
{
    public function entityTag(): ?EntityTag
    {
        $key = $this->getKey();
        $version = $this->entityTagVersion();

        if (! is_scalar($key) || $version === null) {
            return null;
        }

        return EntityTag::strong(hash('xxh128', static::class.'|'.$key.'|'.$version));
    }

    public function lastModified(): ?DateTimeInterface
    {
        // A model can switch updated_at off with `const UPDATED_AT = null`.
        $column = $this->usesTimestamps() ? $this->getUpdatedAtColumn() : null;

        if ($column === null) {
            return null;
        }

        $updatedAt = $this->getAttribute($column);

        return $updatedAt instanceof DateTimeInterface ? $updatedAt : null;
    }

    protected function entityTagVersion(): ?string
    {
        return $this->lastModified()?->format('U.u');
    }
}
