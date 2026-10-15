<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use JustSteveKing\Toolbox\ConditionalRequests\EntityTag;
use JustSteveKing\Toolbox\ConditionalRequests\Outcome;
use JustSteveKing\Toolbox\ConditionalRequests\Preconditions;

function conditional(string $method, array $headers): Request
{
    $request = Request::create('/documents/1', $method);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

const CURRENT = '"v2"';
const MODIFIED = 'Sun, 06 Nov 1994 08:49:37 GMT';

function evaluate(string $method, array $headers, bool $exists = true): Outcome
{
    return Preconditions::evaluate(
        conditional($method, $headers),
        EntityTag::parse(CURRENT),
        new DateTimeImmutable('@784111777'),
        $exists,
    );
}

it('follows the order in RFC 9110 section 13.2.2', function (string $method, array $headers, Outcome $outcome): void {
    expect(evaluate($method, $headers))->toBe($outcome);
})->with([
    // Step 1: If-Match, strong comparison.
    'If-Match current' => ['PATCH', ['If-Match' => '"v2"'], Outcome::Proceed],
    'If-Match among several' => ['PATCH', ['If-Match' => '"v1", "v2"'], Outcome::Proceed],
    'If-Match stale' => ['PATCH', ['If-Match' => '"v1"'], Outcome::PreconditionFailed],
    'If-Match weak never matches' => ['PATCH', ['If-Match' => 'W/"v2"'], Outcome::PreconditionFailed],
    'If-Match any' => ['PATCH', ['If-Match' => '*'], Outcome::Proceed],
    'If-Match malformed' => ['PATCH', ['If-Match' => 'v2'], Outcome::PreconditionFailed],

    // Step 2: If-Unmodified-Since, only without If-Match.
    'unmodified since' => ['PATCH', ['If-Unmodified-Since' => MODIFIED], Outcome::Proceed],
    'modified since' => ['PATCH', ['If-Unmodified-Since' => 'Sat, 05 Nov 1994 08:49:37 GMT'], Outcome::PreconditionFailed],
    'If-Unmodified-Since ignored beside If-Match' => ['PATCH', ['If-Match' => '"v2"', 'If-Unmodified-Since' => 'Sat, 05 Nov 1994 08:49:37 GMT'], Outcome::Proceed],
    'invalid If-Unmodified-Since ignored' => ['PATCH', ['If-Unmodified-Since' => 'last week'], Outcome::Proceed],

    // Step 3: If-None-Match, weak comparison; 304 for GET and HEAD, 412 otherwise.
    'If-None-Match matches on GET' => ['GET', ['If-None-Match' => '"v2"'], Outcome::NotModified],
    'If-None-Match matches weakly on HEAD' => ['HEAD', ['If-None-Match' => 'W/"v2"'], Outcome::NotModified],
    'If-None-Match stale on GET' => ['GET', ['If-None-Match' => '"v1"'], Outcome::Proceed],
    'If-None-Match matches on PUT' => ['PUT', ['If-None-Match' => '"v2"'], Outcome::PreconditionFailed],
    'If-None-Match any on PUT' => ['PUT', ['If-None-Match' => '*'], Outcome::PreconditionFailed],

    // Step 4: If-Modified-Since, GET and HEAD only, and never beside If-None-Match.
    'not modified since' => ['GET', ['If-Modified-Since' => MODIFIED], Outcome::NotModified],
    'modified since, on GET' => ['GET', ['If-Modified-Since' => 'Sat, 05 Nov 1994 08:49:37 GMT'], Outcome::Proceed],
    'If-Modified-Since ignored beside If-None-Match' => ['GET', ['If-None-Match' => '"v1"', 'If-Modified-Since' => MODIFIED], Outcome::Proceed],
    'If-Modified-Since ignored on PATCH' => ['PATCH', ['If-Modified-Since' => MODIFIED], Outcome::Proceed],

    // Step 1 fails before step 3 is reached.
    'If-Match failure wins over If-None-Match' => ['GET', ['If-Match' => '"v1"', 'If-None-Match' => '"v2"'], Outcome::PreconditionFailed],

    'no preconditions' => ['PATCH', [], Outcome::Proceed],
]);

it('answers "*" by whether a current representation exists', function (): void {
    expect(evaluate('PUT', ['If-Match' => '*'], exists: false))->toBe(Outcome::PreconditionFailed)
        ->and(evaluate('PUT', ['If-None-Match' => '*'], exists: false))->toBe(Outcome::Proceed);
});

it('compares modification times in whole seconds, the resolution of an HTTP-date', function (): void {
    $outcome = Preconditions::evaluate(
        conditional('GET', ['If-Modified-Since' => MODIFIED]),
        null,
        new DateTimeImmutable('1994-11-06 08:49:37.900000 UTC'),
    );

    expect($outcome)->toBe(Outcome::NotModified);
});

it('joins repeated fields into one list (RFC 9110 section 5.3)', function (): void {
    $request = conditional('PATCH', []);
    $request->headers->set('If-Match', ['"v1"', '"v2"']);

    expect(Preconditions::evaluate($request, EntityTag::parse(CURRENT), null))->toBe(Outcome::Proceed);
});
