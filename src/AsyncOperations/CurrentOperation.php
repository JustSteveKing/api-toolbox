<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

/**
 * The operation a queued job is running for, so the job can say where its result is:
 *
 *     public function handle(CurrentOperation $operation): void
 *     {
 *         $report = Report::create(...);
 *
 *         $operation->resultAt(route('reports.show', $report));
 *     }
 *
 * The status monitor then answers 303 See Other to that URL. Bound as scoped, so it is reset
 * between jobs on a long-running worker.
 */
final class CurrentOperation
{
    public ?string $id = null;

    public ?string $location = null;

    public function resultAt(string $location): void
    {
        $this->location = $location;
    }
}
