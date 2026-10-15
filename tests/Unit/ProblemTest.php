<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\ProblemDetails\Problem;
use JustSteveKing\Toolbox\Tests\Fixtures\AccountFrozen;
use JustSteveKing\Toolbox\Tests\Fixtures\MissingAttribute;
use JustSteveKing\Toolbox\Tests\Fixtures\OutOfCredit;

it('writes members in RFC order, leaves out nulls, and puts extensions last', function (): void {
    $problem = new Problem(status: 403, type: 'https://example.com/probs/out-of-credit', title: 'No credit', extensions: ['balance' => 30]);

    expect(json_encode($problem, JSON_UNESCAPED_SLASHES))
        ->toBe('{"type":"https://example.com/probs/out-of-credit","title":"No credit","status":403,"balance":30}');
});

it('always writes type, even when it is the about:blank default', function (): void {
    expect(Problem::forStatus(404)->toArray())->toBe(['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404]);
});

it('uses the recommended status phrase as the about:blank title (section 4.2.1)', function (int $status, string $phrase): void {
    expect(Problem::forStatus($status)->title)->toBe($phrase);
})->with([
    [400, 'Bad Request'],
    [409, 'Conflict'],
    [412, 'Precondition Failed'],
    [422, 'Unprocessable Content'],
    [428, 'Precondition Required'],
    [429, 'Too Many Requests'],
    [503, 'Service Unavailable'],
]);

it('rejects an extension that would overwrite a standard member', function (string $name): void {
    new Problem(status: 400, extensions: [$name => 'x']);
})->with(['type', 'title', 'status', 'detail', 'instance'])->throws(InvalidArgumentException::class);

it('rejects extensions without string names', function (): void {
    new Problem(status: 400, extensions: ['a list']);
})->throws(InvalidArgumentException::class);

it('only describes errors', function (int $status): void {
    new Problem(status: $status);
})->with([200, 302, 399, 600])->throws(InvalidArgumentException::class);

it('reads its type from the attribute', function (): void {
    $type = OutOfCredit::problemType();

    expect($type->type)->toBe('out-of-credit')
        ->and($type->title)->toBe('You do not have enough credit.')
        ->and($type->status)->toBe(403)
        ->and((new OutOfCredit)->getMessage())->toBe('You do not have enough credit.')
        ->and((new OutOfCredit('Balance is 30.'))->getMessage())->toBe('Balance is 30.');
});

it('inherits the attribute from a parent problem class', function (): void {
    expect(AccountFrozen::problemType()->type)->toBe('account');
});

it('explains a missing attribute', function (): void {
    new MissingAttribute;
})->throws(LogicException::class, 'needs a #[JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType] attribute');
