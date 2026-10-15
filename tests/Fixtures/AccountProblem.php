<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures;

use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
use JustSteveKing\Toolbox\ProblemDetails\ProblemException;

#[ProblemType('account', title: 'There is a problem with your account.', status: 403)]
abstract class AccountProblem extends ProblemException {}
