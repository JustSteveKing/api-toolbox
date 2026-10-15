<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Contracts;

/**
 * Lets a feature's HTTP exception name its RFC 9457 problem type without depending on the Problem
 * Details feature. When Problem Details is enabled, it uses these; when it is not, Laravel renders
 * the exception by its status as usual.
 *
 * Implemented by exceptions that also implement Symfony's HttpExceptionInterface, which supplies
 * the status and headers.
 */
interface HasProblemType
{
    /**
     * An absolute type URI.
     */
    public function problemType(): string;

    public function problemTitle(): string;
}
