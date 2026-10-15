<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

enum OperationStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function finished(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }
}
