<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures;

use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
use JustSteveKing\Toolbox\ProblemDetails\ProblemException;

#[ProblemType('out-of-credit', title: 'You do not have enough credit.', status: 403)]
final class OutOfCredit extends ProblemException {}
