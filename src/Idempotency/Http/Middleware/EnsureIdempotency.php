<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Idempotency\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Idempotency\Exceptions\IdempotencyKeyConflict;
use JustSteveKing\Toolbox\Idempotency\Exceptions\InvalidIdempotencyKey;
use JustSteveKing\Toolbox\Idempotency\IdempotencyKey;
use JustSteveKing\Toolbox\Idempotency\IdempotencyStore;
use JustSteveKing\Toolbox\Idempotency\StoredResponse;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Makes POST and PATCH safe to retry with an Idempotency-Key
 * (draft-ietf-httpapi-idempotency-key-header-07).
 *
 *     ->middleware('idempotent')            honoured when sent
 *     ->middleware('idempotent:required')   400 when missing
 */
final readonly class EnsureIdempotency
{
    /**
     * The draft is about making non-idempotent methods fault-tolerant (section 1); the others
     * are idempotent already.
     */
    private const array METHODS = ['POST', 'PATCH'];

    public function __construct(
        private IdempotencyStore $store,
        private ?string $documentation = null,
        private int $retryAfter = 1,
    ) {}

    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        if (! in_array($request->getMethod(), self::METHODS, true)) {
            return $this->pass($request, $next);
        }

        $field = $request->headers->get('Idempotency-Key');

        if ($field === null) {
            if (in_array('required', $options, true)) {
                throw InvalidIdempotencyKey::missing($this->documentation);
            }

            return $this->pass($request, $next);
        }

        $key = IdempotencyKey::parse($field) ?? throw InvalidIdempotencyKey::malformed($this->documentation);

        $storageKey = $this->storageKey($request, $key);
        $fingerprint = $this->fingerprint($request);

        if (($stored = $this->store->get($storageKey)) !== null) {
            return $this->replay($stored, $fingerprint);
        }

        $lock = $this->store->lock($storageKey);

        if (! $lock->get()) {
            throw IdempotencyKeyConflict::inFlight($this->retryAfter);
        }

        try {
            // The original may have finished between the first look and taking the lock.
            if (($stored = $this->store->get($storageKey)) !== null) {
                return $this->replay($stored, $fingerprint);
            }

            $response = $this->pass($request, $next);

            $captured = $this->shouldStore($response) ? StoredResponse::capture($fingerprint, $response) : null;

            if ($captured !== null) {
                $this->store->put($storageKey, $captured);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Section 2.6: a retry gets the result of the completed operation, success or error. A reused
     * key with a different payload gets 422 (section 2.7).
     */
    private function replay(StoredResponse $stored, string $fingerprint): Response
    {
        if (! hash_equals($stored->fingerprint, $fingerprint)) {
            throw IdempotencyKeyConflict::differentPayload();
        }

        return $stored->toResponse();
    }

    /**
     * Section 2.2 leaves uniqueness to the resource. Keys are scoped to the authenticated user,
     * or the client address when there is none, so two clients that pick the same key do not
     * collide.
     */
    private function storageKey(Request $request, IdempotencyKey $key): string
    {
        $identifier = $request->user()?->getAuthIdentifier();
        $scope = is_scalar($identifier) ? 'user:'.$identifier : 'ip:'.$request->ip();

        return hash('sha256', $scope."\n".$key->value);
    }

    /**
     * Section 2.4: a checksum of the entire request payload, one of the approaches it lists. The
     * method and target are included, so the same key sent to another endpoint is a different
     * request rather than a retry.
     */
    private function fingerprint(Request $request): string
    {
        return hash('sha256', $request->getMethod()."\n".$request->getRequestUri()."\n".$request->getContent());
    }

    /**
     * A 5xx is not stored, so a request that failed on the server can be retried, which is what
     * the key exists for. Nor is a 429: the request was refused before it ran.
     */
    private function shouldStore(Response $response): bool
    {
        return $response->getStatusCode() < 500 && $response->getStatusCode() !== 429;
    }

    private function pass(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        return $response;
    }
}
