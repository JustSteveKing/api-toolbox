<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use JustSteveKing\Toolbox\Contracts\HasProblemExtensions;
use JustSteveKing\Toolbox\Contracts\HasProblemType;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ProblemFactory
{
    /**
     * The slug used for validation errors when the apiguide.dev catalogue is off.
     */
    public const string VALIDATION_TYPE = 'validation-failed';

    public const string VALIDATION_TITLE = 'Validation Failed';

    /**
     * @param  bool  $apiGuide  Use apiguide.dev types for plain HTTP errors and validation,
     *                          rather than about:blank and a slug against the base URI.
     */
    public function __construct(
        private TypeResolver $types,
        private bool $debug = false,
        private bool $apiGuide = true,
    ) {}

    /**
     * The problem for an exception, or null when the exception is not an error response.
     *
     * Laravel's handler has already turned ModelNotFoundException, AuthorizationException and the
     * like into HTTP exceptions before this runs (Handler::prepareException), so they arrive here
     * as HttpExceptionInterface with the status Laravel chose.
     */
    public function fromThrowable(Throwable $e): ?Problem
    {
        return match (true) {
            $e instanceof ProblemException => $this->fromProblemException($e),
            $e instanceof ValidationException => $this->fromValidationException($e),
            $e instanceof AuthenticationException => $this->forStatus(401, $this->message($e)),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() < 400 => null,
            $e instanceof HttpExceptionInterface && $e instanceof HasProblemType => new Problem(
                status: $e->getStatusCode(),
                type: $this->types->resolve($e->problemType()),
                title: $e->problemTitle(),
                detail: $this->message($e),
                extensions: $e instanceof HasProblemExtensions ? $e->problemExtensions() : [],
            ),
            $e instanceof HttpExceptionInterface => $this->forStatus($e->getStatusCode(), $this->message($e)),
            default => $this->fromUnexpected($e),
        };
    }

    private function fromProblemException(ProblemException $e): Problem
    {
        $type = $e::problemType();

        return new Problem(
            status: $type->status,
            type: $this->types->resolve($type->type),
            title: $type->title,
            detail: $e->detail,
            instance: $e->instance,
            extensions: $e->extensions,
        );
    }

    /**
     * The "errors" extension is the one apiguide.dev documents for its validation-failed type:
     * Laravel's own shape, messages keyed by field. Section 3.2 lets a problem type define its
     * extensions, and a response has to match the type its "type" points at. This is not the
     * list of JSON Pointers in RFC 9457's section 3 example, which is an example of a fictional
     * type rather than a requirement.
     */
    private function fromValidationException(ValidationException $e): Problem
    {
        $type = $this->apiGuide ? ApiGuide::VALIDATION_FAILED : $this->types->resolve(self::VALIDATION_TYPE);

        return new Problem(
            status: $e->status,
            type: $type,
            title: self::VALIDATION_TITLE,
            detail: $this->message($e),
            extensions: ['errors' => $e->errors()],
        );
    }

    /**
     * The apiguide.dev entry for a status where there is one, otherwise about:blank with the status
     * phrase (section 4.2.1).
     *
     * @param  array<string, mixed>  $extensions
     */
    private function forStatus(int $status, ?string $detail = null, array $extensions = []): Problem
    {
        $type = $this->apiGuide ? ApiGuide::forStatus($status) : null;

        if ($type === null) {
            return Problem::forStatus($status, $detail, $extensions);
        }

        return new Problem(
            status: $status,
            type: $type,
            title: ApiGuide::title($type),
            detail: $detail,
            extensions: $extensions,
        );
    }

    /**
     * Section 5 warns against exposing implementation details such as a stack dump, so the
     * message and trace are only included when the application is in debug mode.
     */
    private function fromUnexpected(Throwable $e): Problem
    {
        if (! $this->debug) {
            return $this->forStatus(500);
        }

        return $this->forStatus(500, $this->message($e), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_map(
                static fn (array $frame): array => array_diff_key($frame, ['args' => true]),
                $e->getTrace(),
            ),
        ]);
    }

    private function message(Throwable $e): ?string
    {
        return $e->getMessage() === '' ? null : $e->getMessage();
    }
}
