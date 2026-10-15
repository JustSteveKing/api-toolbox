<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Configuration\Exceptions;
use JustSteveKing\Toolbox\Tests\Fixtures\OutOfCredit;

/**
 * Registers a render callback the way bootstrap/app.php's withExceptions() does: an
 * afterResolving hook on the handler, added before any package provider boots.
 */
abstract class WithApplicationRendererTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app->afterResolving(ExceptionHandler::class, static function ($handler): void {
            (new Exceptions($handler))->render(static fn (OutOfCredit $e) => response('overridden by the app', 402));
        });
    }
}
