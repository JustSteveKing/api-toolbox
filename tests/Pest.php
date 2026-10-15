<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Tests\TestCase;
use JustSteveKing\Toolbox\Tests\WithApplicationRendererTestCase;
use JustSteveKing\Toolbox\Tests\WithOptionsTestCase;
use JustSteveKing\Toolbox\Tests\WithoutFeaturesTestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');
pest()->extend(WithoutFeaturesTestCase::class)->in('Disabled');
pest()->extend(WithApplicationRendererTestCase::class)->in('Override');
pest()->extend(WithOptionsTestCase::class)->in('Options');
