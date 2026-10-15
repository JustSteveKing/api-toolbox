<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class ProblemFactory
{
    public const string VALIDATION_TYPE = 'validation-error';

    public const string VALIDATION_TITLE = 'Your request is not valid.';

    public function __construct(
        private TypeResolver $types,
        private bool $debug = false,
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
            $e instanceof AuthenticationException => Problem::forStatus(401, $this->message($e)),
            $e instanceof HttpExceptionInterface => $e->getStatusCode() >= 400
                ? Problem::forStatus($e->getStatusCode(), $this->message($e))
                : null,
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
     * Follows the shape of the validation example in RFC 9457 section 3: an "errors" extension
     * holding one {detail, pointer} object per message.
     *
     * The RFC's pointers locate the problem "within the request's content". Laravel validates
     * query parameters and content through the same keys, so a pointer here locates the field in
     * the validated input, whichever part of the request it came from.
     */
    private function fromValidationException(ValidationException $e): Problem
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach (is_array($messages) ? $messages : [] as $message) {
                if (is_string($message)) {
                    $errors[] = [
                        'detail' => $message,
                        'pointer' => JsonPointer::fromDotPath((string) $field),
                    ];
                }
            }
        }

        return new Problem(
            status: $e->status,
            type: $this->types->resolve(self::VALIDATION_TYPE),
            title: self::VALIDATION_TITLE,
            detail: $this->message($e),
            extensions: ['errors' => $errors],
        );
    }

    /**
     * Section 5 warns against exposing implementation details such as a stack dump, so the
     * message and trace are only included when the application is in debug mode.
     */
    private function fromUnexpected(Throwable $e): Problem
    {
        if (! $this->debug) {
            return Problem::forStatus(500);
        }

        return Problem::forStatus(500, $this->message($e), [
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
