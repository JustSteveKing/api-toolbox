<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Contracts\RequestIdentifier;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ProblemRenderer
{
    private const string JSON_API = 'application/vnd.api+json';

    /**
     * @param  list<string>  $paths  Request path patterns that always get problem details.
     * @param  string|null  $challenge  The WWW-Authenticate challenge for a 401 that has none.
     */
    public function __construct(
        private ProblemFactory $factory,
        private array $paths = [],
        private ?string $challenge = null,
        private ?RequestIdentifier $requestId = null,
    ) {}

    /**
     * The problem response for an exception, or null to let Laravel render it as usual.
     */
    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        // An HttpResponseException already carries the exact response someone chose to send.
        if ($e instanceof HttpResponseException || ! $this->shouldRender($request)) {
            return null;
        }

        $problem = $this->factory->fromThrowable($e);

        if ($problem === null) {
            return null;
        }

        $problem = $this->withInstance($problem);

        $headers = $this->headersFrom($e);

        // RFC 9110 section 15.5.2: a 401 MUST carry a WWW-Authenticate field with at least one
        // challenge. Laravel's AuthenticationException carries none, so the configured one is
        // added. An exception that already brings its own challenge keeps it.
        if ($problem->status === 401 && $this->challenge !== null && ! $this->hasChallenge($headers)) {
            $headers['WWW-Authenticate'] = $this->challenge;
        }

        return $this->respond($problem, $headers);
    }

    /**
     * Section 3.1.2: the generator MUST use the same status in the response as in the object.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public function respond(Problem $problem, array $headers = []): JsonResponse
    {
        $response = new JsonResponse(
            $problem->toArray(),
            $problem->status,
            $headers,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        $response->headers->set('Content-Type', Problem::MEDIA_TYPE);

        return $response;
    }

    /**
     * The headers an HTTP exception carries, such as Retry-After or Allow. Symfony types them
     * only as an array, and Laravel's ThrottleRequests sets Retry-After as an int.
     *
     * @return array<string, string|list<string>>
     */
    private function headersFrom(Throwable $e): array
    {
        if (! $e instanceof HttpExceptionInterface) {
            return [];
        }

        $headers = [];

        foreach ($e->getHeaders() as $name => $value) {
            if (is_string($value) || is_int($value)) {
                $headers[(string) $name] = (string) $value;
            } elseif (is_array($value)) {
                $headers[(string) $name] = array_values(array_filter($value, is_string(...)));
            }
        }

        return $headers;
    }

    /**
     * RFC 9457 section 3.1.5: "instance" identifies this occurrence of the problem, and need not be
     * dereferenceable. The request ID does exactly that, and a UUID has a standard URI form,
     * urn:uuid (RFC 9562, section 4). An ID that is not a UUID, such as one a proxy assigned, has
     * no such form and is left out rather than made into an invalid URI. An instance the problem
     * already has is kept.
     */
    private function withInstance(Problem $problem): Problem
    {
        $id = $this->requestId?->current();

        if ($problem->instance !== null || $id === null || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) !== 1) {
            return $problem;
        }

        return new Problem($problem->status, $problem->type, $problem->title, $problem->detail, 'urn:uuid:'.strtolower($id), $problem->extensions);
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    private function hasChallenge(array $headers): bool
    {
        foreach (array_keys($headers) as $name) {
            if (strcasecmp($name, 'WWW-Authenticate') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * JSON:API (Laravel 13's JsonApiResource) defines its own error document, so a request
     * speaking it is left alone. Section 4 of RFC 9457 says as much: problem details are "not to
     * replace existing domain-specific formats".
     */
    private function shouldRender(Request $request): bool
    {
        if ($this->speaksJsonApi($request)) {
            return false;
        }

        return $request->expectsJson() || ($this->paths !== [] && $request->is(...$this->paths));
    }

    private function speaksJsonApi(Request $request): bool
    {
        return str_contains((string) $request->header('Accept'), self::JSON_API)
            || str_contains((string) $request->header('Content-Type'), self::JSON_API);
    }
}
