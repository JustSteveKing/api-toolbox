<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Tests\AppMonitorRouteTestCase;
use JustSteveKing\Toolbox\Tests\OutsideTestsTestCase;
use JustSteveKing\Toolbox\Tests\TestCase;
use JustSteveKing\Toolbox\Tests\WithApplicationRendererTestCase;
use JustSteveKing\Toolbox\Tests\WithOptionsTestCase;
use JustSteveKing\Toolbox\Tests\WithoutFeaturesTestCase;
use JustSteveKing\Toolbox\Tests\WithSignatureKeysTestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Memory');
pest()->extend(WithoutFeaturesTestCase::class)->in('Disabled');
pest()->extend(WithApplicationRendererTestCase::class)->in('Override');
pest()->extend(WithOptionsTestCase::class)->in('Options');
pest()->extend(AppMonitorRouteTestCase::class)->in('AppMonitorRoute');
pest()->extend(OutsideTestsTestCase::class)->in('Production');
pest()->extend(WithSignatureKeysTestCase::class)->in('Signatures');
