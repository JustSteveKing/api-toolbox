<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use JustSteveKing\Toolbox\AsyncOperations\CurrentOperation;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class GenerateReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public string $outcome = 'succeed',
    ) {}

    public function handle(CurrentOperation $operation): void
    {
        match ($this->outcome) {
            'succeed' => $operation->resultAt('https://api.example.com/reports/42'),
            'succeed-without-result' => null,
            'unexpected' => throw new RuntimeException('database password is hunter2'),
            'http' => throw new HttpException(422, 'The report range is empty.'),
            default => null,
        };
    }
}
