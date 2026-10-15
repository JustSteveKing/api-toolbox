<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\AssertionFailedError;

function fakeResponse(string $body = '', int $status = 200, array $headers = []): TestResponse
{
    return TestResponse::fromBaseResponse(new Response($body, $status, $headers));
}

function fakeProblem(array $body, int $status = 403, string $contentType = 'application/problem+json'): TestResponse
{
    return fakeResponse(json_encode($body, JSON_THROW_ON_ERROR), $status, ['Content-Type' => $contentType]);
}

describe('assertProblem', function (): void {
    it('passes for a well-formed problem', function (): void {
        fakeProblem(['type' => 'https://example.com/probs/out-of-credit', 'title' => 'No credit', 'status' => 403])
            ->assertProblem()
            ->assertProblem(403)
            ->assertProblemType('https://example.com/probs/out-of-credit', 403);
    });

    it('treats a missing type as about:blank', function (): void {
        fakeProblem(['title' => 'Forbidden'])->assertProblemType('about:blank');
    });

    it('accepts media type parameters', function (): void {
        fakeProblem(['type' => 'about:blank'], contentType: 'application/problem+json; charset=utf-8')->assertProblem();
    });

    it('fails', function (TestResponse $response): void {
        $response->assertProblem();
    })->with([
        'wrong media type' => fn () => fakeProblem(['type' => 'about:blank'], contentType: 'application/json'),
        'status disagrees with the response' => fn () => fakeProblem(['status' => 400], 403),
        'status is a string' => fn () => fakeProblem(['status' => '403']),
        'title is not a string' => fn () => fakeProblem(['title' => ['No credit']]),
        'a JSON array' => fn () => fakeProblem([['type' => 'about:blank']]),
        'not JSON' => fn () => fakeResponse('nope', 403, ['Content-Type' => 'application/problem+json']),
    ])->throws(AssertionFailedError::class);

    it('fails on the wrong type', function (): void {
        fakeProblem(['type' => 'https://example.com/a'])->assertProblemType('https://example.com/b');
    })->throws(AssertionFailedError::class);
});

describe('assertDeprecated and assertSunset', function (): void {
    it('pass for the RFC 9745 examples, with the Sunset date written correctly', function (): void {
        fakeResponse(headers: [
            'Deprecation' => '@1688169599',
            'Sunset' => 'Sun, 30 Jun 2024 23:59:59 GMT',
            'Link' => '<https://developer.example.com/deprecation>; rel="deprecation"; type="text/html"',
        ])
            ->assertDeprecated()
            ->assertDeprecated('2023-06-30T23:59:59Z', 'https://developer.example.com/deprecation')
            ->assertDeprecated(new DateTimeImmutable('@1688169599'))
            ->assertSunset('2024-06-30T23:59:59Z');
    });

    it('fail on malformed or missing fields', function (Closure $assert): void {
        $assert();
    })->with([
        'no Deprecation' => fn () => fakeResponse()->assertDeprecated(),
        'Deprecation as an HTTP-date' => fn () => fakeResponse(headers: ['Deprecation' => 'Fri, 30 Jun 2023 23:59:59 GMT'])->assertDeprecated(),
        'Deprecation as true' => fn () => fakeResponse(headers: ['Deprecation' => 'true'])->assertDeprecated(),
        'wrong deprecation date' => fn () => fakeResponse(headers: ['Deprecation' => '@1688169599'])->assertDeprecated('2024-01-01'),
        'missing deprecation link' => fn () => fakeResponse(headers: ['Deprecation' => '@1688169599'])->assertDeprecated(link: 'https://a.example'),
        'Sunset in UTC, as RFC 9745 section 4 writes it' => fn () => fakeResponse(headers: ['Sunset' => 'Sun, 30 Jun 2024 23:59:59 UTC'])->assertSunset(),
        'Sunset as a Structured Field Date' => fn () => fakeResponse(headers: ['Sunset' => '@1719791999'])->assertSunset(),
        'Sunset before Deprecation' => fn () => fakeResponse(headers: ['Deprecation' => '@1719791999', 'Sunset' => 'Fri, 30 Jun 2023 23:59:59 GMT'])->assertSunset(),
        'two Deprecation fields' => fn () => fakeResponse(headers: ['Deprecation' => ['@1', '@2']])->assertDeprecated(),
    ])->throws(AssertionFailedError::class);

    it('asserts the absence of both', function (): void {
        fakeResponse()->assertNotDeprecated();
    });

    it('fails assertNotDeprecated on a sunset alone', function (): void {
        fakeResponse(headers: ['Sunset' => 'Sun, 30 Jun 2024 23:59:59 GMT'])->assertNotDeprecated();
    })->throws(AssertionFailedError::class);
});

describe('assertLinkHeader', function (): void {
    it('finds a link by relation type and target', function (): void {
        fakeResponse(headers: ['Link' => ['<https://a.example/2>; rel="next"', '<https://a.example/0>; rel="prev"']])
            ->assertLinkHeader('next')
            ->assertLinkHeader('prev', 'https://a.example/0')
            ->assertNoLinkHeader('first');
    });

    it('fails', function (Closure $assert): void {
        $assert();
    })->with([
        'no such relation' => fn () => fakeResponse(headers: ['Link' => '<https://a.example>; rel="next"'])->assertLinkHeader('prev'),
        'different target' => fn () => fakeResponse(headers: ['Link' => '<https://a.example>; rel="next"'])->assertLinkHeader('next', 'https://b.example'),
        'malformed field' => fn () => fakeResponse(headers: ['Link' => 'https://a.example; rel="next"'])->assertLinkHeader('next'),
        'present when it should not be' => fn () => fakeResponse(headers: ['Link' => '<https://a.example>; rel="next"'])->assertNoLinkHeader('next'),
    ])->throws(AssertionFailedError::class);
});

describe('conditional request assertions', function (): void {
    it('pass for well-formed responses', function (): void {
        fakeResponse(headers: ['ETag' => '"xyzzy"'])->assertETag()->assertETag('"xyzzy"', weak: false);
        fakeResponse(headers: ['ETag' => 'W/"xyzzy"'])->assertETag(weak: true);
        fakeResponse(headers: ['ETag' => '""'])->assertETag();
        fakeResponse(status: 304)->assertNotModified();
        fakeResponse(status: 412)->assertPreconditionFailed();
        fakeResponse('Retry with If-Match.', 428, ['Cache-Control' => 'no-store, private'])->assertPreconditionRequired();
    });

    it('fail', function (Closure $assert): void {
        $assert();
    })->with([
        'no ETag' => fn () => fakeResponse()->assertETag(),
        'unquoted ETag' => fn () => fakeResponse(headers: ['ETag' => 'xyzzy'])->assertETag(),
        'lowercase weak prefix' => fn () => fakeResponse(headers: ['ETag' => 'w/"xyzzy"'])->assertETag(),
        'quote inside ETag' => fn () => fakeResponse(headers: ['ETag' => '"xy"zzy"'])->assertETag(),
        'strong when weak expected' => fn () => fakeResponse(headers: ['ETag' => '"xyzzy"'])->assertETag(weak: true),
        'different ETag' => fn () => fakeResponse(headers: ['ETag' => '"a"'])->assertETag('"b"'),
        '200 not 304' => fn () => fakeResponse()->assertNotModified(),
        '428 without explanation' => fn () => fakeResponse('', 428, ['Cache-Control' => 'no-store'])->assertPreconditionRequired(),
        '428 a cache could store' => fn () => fakeResponse('Retry with If-Match.', 428)->assertPreconditionRequired(),
    ])->throws(AssertionFailedError::class);
});

describe('Pest expectations', function (): void {
    it('mirror the macros', function (): void {
        expect(fakeProblem(['type' => 'https://example.com/a', 'status' => 403]))
            ->toBeProblem(403)
            ->toHaveProblemType('https://example.com/a');

        expect(fakeResponse(headers: [
            'Deprecation' => '@1688169599',
            'Sunset' => 'Sun, 30 Jun 2024 23:59:59 GMT',
            'Link' => '<https://a.example>; rel="sunset"',
        ]))
            ->toBeDeprecated('2023-06-30T23:59:59Z')
            ->toHaveSunset(link: 'https://a.example')
            ->toHaveLink('sunset');

        expect(fakeResponse(headers: ['ETag' => '"xyzzy"']))->toHaveETag('"xyzzy"');
        expect(fakeResponse(status: 304))->toBeNotModified();
        expect(fakeResponse(status: 412))->toBePreconditionFailed();
        expect(fakeResponse('Retry.', 428, ['Cache-Control' => 'no-store']))->toBePreconditionRequired();
    });

    it('fail on something that is not a response', function (): void {
        expect('a string')->toBeProblem();
    })->throws(AssertionFailedError::class, 'toBeProblem() expects a TestResponse.');
});
