<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Exceptions;

use RuntimeException;

/**
 * RFC 9421 section 2.5: a covered component that cannot be resolved MUST produce an error and no
 * signature base.
 */
final class SignatureBaseException extends RuntimeException {}
