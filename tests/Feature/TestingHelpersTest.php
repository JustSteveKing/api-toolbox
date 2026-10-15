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

describe('idempotency and rate limit assertions', function (): void {
    it('accepts an identical replay, ignoring Date', function (): void {
        $original = fakeResponse('{"id":1}', 201, ['Location' => '/a/1', 'Date' => 'Thu, 15 Oct 2026 09:00:00 GMT']);
        $replay = fakeResponse('{"id":1}', 201, ['Location' => '/a/1', 'Date' => 'Thu, 15 Oct 2026 09:00:05 GMT']);

        $replay->assertIdempotentReplay($original);
        expect($replay)->toBeIdempotentReplayOf($original);
    });

    it('rejects what is not a replay', function (Closure $assert): void {
        $assert();
    })->with([
        'different status' => fn () => fakeResponse('{"id":1}', 200)->assertIdempotentReplay(fakeResponse('{"id":1}', 201)),
        'different content' => fn () => fakeResponse('{"id":2}', 201)->assertIdempotentReplay(fakeResponse('{"id":1}', 201)),
        'missing header' => fn () => fakeResponse('{"id":1}', 201)->assertIdempotentReplay(fakeResponse('{"id":1}', 201, ['Location' => '/a/1'])),
    ])->throws(AssertionFailedError::class);

    it('accepts valid RateLimit fields', function (): void {
        $response = fakeResponse(headers: [
            'RateLimit-Policy' => '"burst";q=100;w=60, "daily";q=1000;w=86400;qu="requests";pk=:cHsdsRa894==:',
            'RateLimit' => '"burst";r=50;t=30, "daily";r=999',
        ]);

        $response->assertRateLimitHeaders()->assertRateLimitHeaders('burst', remaining: 50);
        expect($response)->toHaveRateLimitHeaders('daily', 999);
    });

    it('rejects invalid RateLimit fields', function (array $headers, ?string $policy = null, ?int $remaining = null): void {
        fakeResponse(headers: $headers)->assertRateLimitHeaders($policy, $remaining);
    })->with([
        'no fields' => [[]],
        'policy only' => [['RateLimit-Policy' => '"a";q=1']],
        'policy as a token' => [['RateLimit-Policy' => 'a;q=1', 'RateLimit' => '"a";r=1']],
        'no q' => [['RateLimit-Policy' => '"a";w=60', 'RateLimit' => '"a";r=1']],
        'zero window' => [['RateLimit-Policy' => '"a";q=1;w=0', 'RateLimit' => '"a";r=1']],
        'negative remaining' => [['RateLimit-Policy' => '"a";q=1', 'RateLimit' => '"a";r=-1']],
        'no r' => [['RateLimit-Policy' => '"a";q=1', 'RateLimit' => '"a";t=1']],
        'pk not bytes' => [['RateLimit-Policy' => '"a";q=1;pk="user"', 'RateLimit' => '"a";r=1']],
        'old draft syntax' => [['RateLimit-Policy' => '100;w=60', 'RateLimit' => 'limit=100, remaining=50, reset=30']],
        'missing policy' => [['RateLimit-Policy' => '"a";q=1', 'RateLimit' => '"a";r=1'], 'b'],
        'wrong remaining' => [['RateLimit-Policy' => '"a";q=5', 'RateLimit' => '"a";r=1'], 'a', 4],
    ])->throws(AssertionFailedError::class);
});

describe('negotiation assertions', function (): void {
    it('accept a valid traceparent and request ID', function (): void {
        $response = fakeResponse(headers: [
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
            'X-Request-Id' => 'abc',
        ]);

        $response->assertTraceparent()->assertTraceparent('4bf92f3577b34da6a3ce929d0e0e4736')->assertRequestId('abc');
        expect($response)->toHaveTraceparent()->toHaveRequestId();
    });

    it('reject', function (Closure $assert): void {
        $assert();
    })->with([
        'no traceparent' => fn () => fakeResponse()->assertTraceparent(),
        'uppercase' => fn () => fakeResponse(headers: ['traceparent' => '00-4BF92F3577B34DA6A3CE929D0E0E4736-00f067aa0ba902b7-01'])->assertTraceparent(),
        'zero trace-id' => fn () => fakeResponse(headers: ['traceparent' => '00-00000000000000000000000000000000-00f067aa0ba902b7-01'])->assertTraceparent(),
        'other trace' => fn () => fakeResponse(headers: ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'])->assertTraceparent(str_repeat('1', 32)),
        'no request ID' => fn () => fakeResponse()->assertRequestId(),
        'other request ID' => fn () => fakeResponse(headers: ['X-Request-Id' => 'a'])->assertRequestId('b'),
    ])->throws(AssertionFailedError::class);
});

describe('cache-control and preference assertions', function (): void {
    it('accept what is there', function (): void {
        fakeResponse(headers: ['Cache-Control' => 'public, Max-Age=60, stale-while-revalidate="30", immutable'])
            ->assertCacheControl(['public', 'max-age' => 60, 'stale-while-revalidate' => '30', 'immutable']);

        fakeResponse(headers: ['Preference-Applied' => 'return=minimal, respond-async'])
            ->assertPreferenceApplied('return', 'minimal')
            ->assertPreferenceApplied('respond-async')
            ->assertPreferenceNotApplied('handling');

        expect(fakeResponse('{}', 202, ['Location' => '/operations/1', 'Retry-After' => '5'])->assertOperationAccepted())->toBe('/operations/1');
    });

    it('reject', function (Closure $assert): void {
        $assert();
    })->with([
        'missing directive' => fn () => fakeResponse(headers: ['Cache-Control' => 'public'])->assertCacheControl(['immutable']),
        'wrong value' => fn () => fakeResponse(headers: ['Cache-Control' => 'max-age=60'])->assertCacheControl(['max-age' => 30]),
        'not applied' => fn () => fakeResponse()->assertPreferenceApplied('return'),
        'other value' => fn () => fakeResponse(headers: ['Preference-Applied' => 'return=minimal'])->assertPreferenceApplied('return', 'representation'),
        'applied when it should not be' => fn () => fakeResponse(headers: ['Preference-Applied' => 'respond-async'])->assertPreferenceNotApplied('respond-async'),
        '202 without Location' => fn () => fakeResponse('{}', 202, ['Retry-After' => '5'])->assertOperationAccepted(),
        '202 without Retry-After' => fn () => fakeResponse('{}', 202, ['Location' => '/o/1'])->assertOperationAccepted(),
        'not a 202' => fn () => fakeResponse('{}', 200, ['Location' => '/o/1', 'Retry-After' => '5'])->assertOperationAccepted(),
    ])->throws(AssertionFailedError::class);
});

describe('digest assertions', function (): void {
    it('accept digests that match the content', function (): void {
        $digest = 'sha-256=:'.base64_encode(hash('sha256', 'hello', true)).':, md5=:XUFAKrxLKna5cZ2REBfFkg==:';

        fakeResponse('hello', headers: ['Content-Digest' => $digest, 'Repr-Digest' => $digest, 'Accept-Patch' => 'application/merge-patch+json; charset=utf-8'])
            ->assertContentDigest()
            ->assertReprDigest()
            ->assertAcceptsPatch();
    });

    it('reject', function (Closure $assert): void {
        $assert();
    })->with([
        'no digest' => fn () => fakeResponse('hello')->assertContentDigest(),
        'mismatch' => fn () => fakeResponse('hello', headers: ['Repr-Digest' => 'sha-256=:'.base64_encode(hash('sha256', 'bye', true)).':'])->assertReprDigest(),
        'not a byte sequence' => fn () => fakeResponse('hello', headers: ['Content-Digest' => 'sha-256="abc"'])->assertContentDigest(),
        'nothing checkable' => fn () => fakeResponse('hello', headers: ['Content-Digest' => 'md5=:XUFAKrxLKna5cZ2REBfFkg==:'])->assertContentDigest(),
        'no Accept-Patch' => fn () => fakeResponse()->assertAcceptsPatch(),
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
