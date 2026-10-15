<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use InvalidArgumentException;
use JustSteveKing\Toolbox\AsyncOperations\Jobs\TrackOperation;
use JustSteveKing\Toolbox\Contracts\ClientPreferences;

/**
 * Runs a queued job as an asynchronous operation and answers 202 Accepted.
 *
 *     public function store(Request $request, Operations $operations, Preferences $preferences)
 *     {
 *         if ($preferences->respondAsync()) {
 *             return $operations->dispatch(new GenerateReport($request->validated()));
 *         }
 *
 *         return new ReportResource((new GenerateReport($request->validated()))->handle());
 *     }
 */
final readonly class Operations
{
    public const string ROUTE = 'api-toolbox.operations.show';

    public function __construct(
        private OperationStore $store,
        private Dispatcher $bus,
        private UrlGenerator $urls,
        private Request $request,
        private int $retryAfter,
        private ?ClientPreferences $preferences = null,
    ) {}

    /**
     * The job needs Laravel's Queueable trait, whose $middleware property carries the tracking.
     */
    public function dispatch(object $job): Accepted
    {
        if (! property_exists($job, 'middleware')) {
            throw new InvalidArgumentException(sprintf('%s needs the Queueable trait to run as an operation.', $job::class));
        }

        $operation = $this->store->create($this->owner());

        /** @var array<int, object> $middleware */
        $middleware = is_array($job->middleware) ? $job->middleware : [];
        $job->middleware = [...$middleware, new TrackOperation($operation->id)];

        $this->bus->dispatch($job);

        // A sync queue runs the job before dispatch() returns, so report what is now true.
        $operation = $this->store->find($operation->id) ?? $operation;

        $honoured = $this->preferences?->prefers('respond-async') ?? false;

        if ($honoured) {
            $this->preferences?->applied('respond-async');
        }

        return new Accepted($operation, $this->urls->route(self::ROUTE, ['operation' => $operation->id]), $this->retryAfter, $honoured);
    }

    /**
     * Only whoever started an operation can see it, when there was someone signed in to start it.
     */
    private function owner(): ?string
    {
        $identifier = $this->request->user()?->getAuthIdentifier();

        return is_scalar($identifier) ? (string) $identifier : null;
    }
}
