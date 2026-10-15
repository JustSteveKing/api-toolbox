<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Contracts;

interface EnabledFeatures
{
    public function enabled(string $feature): bool;

    /**
     * The options passed inline to the feature in config, or an empty array.
     *
     * @return array<string, mixed>
     */
    public function options(string $feature): array;

    /**
     * @return list<string>
     */
    public function all(): array;
}
