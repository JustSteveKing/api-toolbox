<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\AsyncOperations;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * 202 Accepted (RFC 9110, section 15.3.3), whose representation "ought to describe the request's
 * current status and point to ... a status monitor". Location points at the monitor, as RFC 7240
 * section 4.1's example does, and Retry-After says when it is worth asking.
 */
final readonly class Accepted implements Responsable
{
    public function __construct(
        private Operation $operation,
        private string $monitor,
        private int $retryAfter,
        private bool $preferenceApplied,
    ) {}

    public function toResponse($request): JsonResponse
    {
        $headers = [
            'Location' => $this->monitor,
            'Content-Location' => $this->monitor,
            'Retry-After' => (string) $this->retryAfter,
        ];

        if ($this->preferenceApplied) {
            $headers['Preference-Applied'] = 'respond-async';
        }

        return new JsonResponse($this->operation->toRepresentation(), 202, $headers, JSON_UNESCAPED_SLASHES);
    }
}
