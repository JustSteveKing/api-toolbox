<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('JustSteveKing\Toolbox')
    ->toUseStrictTypes();

arch('classes are final unless they are extension points')
    ->expect('JustSteveKing\Toolbox')
    ->classes()
    ->toBeFinal()
    ->ignoring('JustSteveKing\Toolbox\ProblemDetails\ProblemException');

arch('internals inject contracts rather than calling facades')
    ->expect('JustSteveKing\Toolbox')
    ->not->toUse('Illuminate\Support\Facades')
    ->ignoring('JustSteveKing\Toolbox\Facades');

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
