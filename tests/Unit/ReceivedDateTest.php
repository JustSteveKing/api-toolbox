<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\ConditionalRequests\ReceivedDate;

it('accepts all three forms RFC 9110 section 5.6.7 says a recipient must', function (string $value): void {
    expect(ReceivedDate::parse($value)?->getTimestamp())->toBe(784111777);
})->with([
    'IMF-fixdate' => ['Sun, 06 Nov 1994 08:49:37 GMT'],
    'RFC 850' => ['Sunday, 06-Nov-94 08:49:37 GMT'],
    'asctime' => ['Sun Nov  6 08:49:37 1994'],
]);

it('ignores what is not an HTTP-date, including a list of them', function (?string $value): void {
    expect(ReceivedDate::parse($value))->toBeNull();
})->with([
    null,
    '',
    'yesterday',
    '1994-11-06T08:49:37Z',
    'Sun, 06 Nov 1994 08:49:37 GMT, Mon, 07 Nov 1994 08:49:37 GMT',
]);
