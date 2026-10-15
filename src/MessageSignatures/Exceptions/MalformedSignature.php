<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures\Exceptions;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * 400 for Signature or Signature-Input fields that do not parse, as apiguide.dev's Signature page
 * recommends: the request is malformed, not unauthenticated.
 */
final class MalformedSignature extends BadRequestHttpException {}
