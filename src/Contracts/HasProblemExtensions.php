<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Contracts;

/**
 * Extension members (RFC 9457, section 3.2) a feature's HTTP exception adds to its problem,
 * alongside HasProblemType, without depending on the Problem Details feature.
 */
interface HasProblemExtensions
{
    /**
     * @return array<string, mixed>
     */
    public function problemExtensions(): array;
}
