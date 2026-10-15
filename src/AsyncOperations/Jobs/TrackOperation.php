<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations\Jobs;

use Closure;
use Illuminate\Container\Container;
use JustSteveKing\Toolbox\AsyncOperations\CurrentOperation;
use JustSteveKing\Toolbox\AsyncOperations\OperationStatus;
use JustSteveKing\Toolbox\AsyncOperations\OperationStore;
use JustSteveKing\Toolbox\Contracts\HasProblemType;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Job middleware that keeps an operation's status in step with the job running it.
 *
 * Attached by Operations::dispatch() through the job's $middleware property, so the job class
 * needs nothing more than Laravel's Queueable trait.
 */
final readonly class TrackOperation
{
    public function __construct(
        public string $operation,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        // Job middleware is serialised with the job, so it cannot hold services; it resolves them
        // when the job runs.
        $container = Container::getInstance();
        $store = $container->make(OperationStore::class);
        $operation = $store->find($this->operation);

        if ($operation === null) {
            return $next($job);
        }

        $current = $container->make(CurrentOperation::class);
        $current->id = $operation->id;
        $current->location = null;

        $store->save($operation->with(OperationStatus::Running));

        try {
            $result = $next($job);
        } catch (Throwable $e) {
            // A job with attempts left goes back to pending; one without has failed. A later
            // attempt that succeeds overwrites either.
            $store->save($operation->with(
                $this->hasAttemptsLeft($job) ? OperationStatus::Pending : OperationStatus::Failed,
                error: $this->hasAttemptsLeft($job) ? null : self::problem($e),
            ));

            throw $e;
        } finally {
            $location = $current->location;
            $current->id = null;
            $current->location = null;
        }

        $store->save($operation->with(OperationStatus::Succeeded, $location));

        return $result;
    }

    private function hasAttemptsLeft(object $job): bool
    {
        $attempts = method_exists($job, 'attempts') ? $job->attempts() : 1;
        $tries = property_exists($job, 'tries') && is_int($job->tries) ? $job->tries : null;

        return $tries !== null && is_int($attempts) && $attempts < $tries;
    }

    /**
     * A problem object for the status monitor. RFC 9457 section 5: an unexpected exception's
     * message can leak implementation details, so only an HTTP exception's message is shown.
     *
     * @return array<string, mixed>
     */
    private static function problem(Throwable $e): array
    {
        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $type = $e instanceof HasProblemType ? $e->problemType() : (ApiGuide::forStatus($status) ?? 'about:blank');
        $title = $e instanceof HasProblemType ? $e->problemTitle() : (ApiGuide::title($type) ?? 'Operation failed');

        return array_filter([
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $e instanceof HttpExceptionInterface && $e->getMessage() !== '' ? $e->getMessage() : null,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
