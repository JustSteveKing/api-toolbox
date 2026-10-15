<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\AsyncOperations\OperationStatus;
use JustSteveKing\Toolbox\AsyncOperations\OperationStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The status monitor an asynchronous operation's 202 points at.
 *
 *     pending, running   200 with the status, and Retry-After
 *     succeeded          303 See Other to the result, when the job named one; otherwise 200
 *     failed             200 with the status and an "error" problem object
 *
 * The monitor itself exists while the operation is known, so it answers 200 even for a failed
 * operation: the request for the monitor succeeded. RFC 9110 section 15.4.4 makes 303 the way to
 * point "to a different resource ... intended to provide an indirect response to the original
 * request", which a finished operation's result is.
 */
final readonly class ShowOperation
{
    public function __construct(
        private OperationStore $store,
        private int $retryAfter,
    ) {}

    public function __invoke(Request $request, string $operation): JsonResponse|RedirectResponse
    {
        $found = $this->store->find($operation);
        $user = $request->user()?->getAuthIdentifier();

        // Someone else's operation is answered exactly as one that does not exist.
        if ($found === null || ($found->owner !== null && (! is_scalar($user) || (string) $user !== $found->owner))) {
            throw new NotFoundHttpException('No operation with this ID is known.');
        }

        if ($found->status === OperationStatus::Succeeded && $found->location !== null) {
            return new RedirectResponse($found->location, 303);
        }

        $response = new JsonResponse($found->toRepresentation(), 200, [], JSON_UNESCAPED_SLASHES);

        if (! $found->status->finished()) {
            $response->headers->set('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }
}
