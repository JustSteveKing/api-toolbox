<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails;

use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
use LogicException;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Extend this for each problem type, and describe the type with #[ProblemType]:
 *
 *     #[ProblemType('out-of-credit', title: 'You do not have enough credit.', status: 403)]
 *     final class OutOfCredit extends ProblemException {}
 *
 *     throw new OutOfCredit(detail: 'Your balance is 30, but that costs 50.', extensions: ['balance' => 30]);
 *
 * The type, title and status belong to the class because section 3.1.3 says the title SHOULD NOT
 * change between occurrences. Detail, instance and extensions belong to the occurrence.
 */
abstract class ProblemException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $extensions
     */
    public function __construct(
        public readonly ?string $detail = null,
        public readonly array $extensions = [],
        public readonly ?string $instance = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($detail ?? static::problemType()->title, 0, $previous);
    }

    /**
     * The #[ProblemType] on this class, or on the nearest parent that has one.
     */
    public static function problemType(): ProblemType
    {
        for ($class = new ReflectionClass(static::class); $class !== false; $class = $class->getParentClass()) {
            $attributes = $class->getAttributes(ProblemType::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        throw new LogicException(sprintf('%s needs a #[%s] attribute.', static::class, ProblemType::class));
    }
}
