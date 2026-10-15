<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

const BODY = "{\"hello\": \"world\"}\n";
const SHA256 = 'sha-256=:RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg=:';

beforeEach(function (): void {
    Route::put('api/items/123', fn () => response()->noContent())->middleware('digest.verify');
    Route::put('api/strict/123', fn () => response()->noContent())->middleware('digest.verify:required');
    Route::get('api/items/123', fn () => response(BODY, 200, ['Content-Type' => 'application/json']))->middleware('digest.respond');
    Route::get('api/always/123', fn () => response(BODY, 200, ['Content-Type' => 'application/json']))->middleware('digest.respond:always');
});

function putWith(PHPUnit\Framework\TestCase $test, array $headers, string $uri = 'api/items/123', string $body = BODY): Illuminate\Testing\TestResponse
{
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return $test->call('PUT', $uri, [], [], [], $server, $body);
}

describe('verifying requests', function (): void {
    it('accepts content whose digest matches (RFC 9530 Appendix B)', function (string $field): void {
        putWith($this, [$field => SHA256])->assertNoContent();
    })->with(['Content-Digest', 'Repr-Digest']);

    it('ignores algorithms it does not support beside one it does (section 2)', function (): void {
        putWith($this, ['Content-Digest' => 'md5=:XrY7u+Ae7tCTyyK7j1rNww==:, '.SHA256])->assertNoContent();
    });

    it('refuses a mismatch with the draft\'s problem type and members, never its own digest', function (): void {
        $response = putWith($this, ['Content-Digest' => SHA256], body: '{"hello": "there"}');

        $response->assertStatus(400)
            ->assertProblemType('https://apiguide.dev/errors/digest-mismatched-values')
            ->assertJsonPath('title', 'Mismatched Digest Values')
            ->assertJsonPath('mismatched_digests', [[
                'algorithm' => 'sha-256',
                'provided_digest' => ':RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg=:',
                'header' => 'Content-Digest',
            ]]);

        expect($response->getContent())->not->toContain(base64_encode(hash('sha256', '{"hello": "there"}', true)));
    });

    it('refuses a digest of the wrong length as invalid, without hashing', function (): void {
        putWith($this, ['Repr-Digest' => 'sha-512=:'.base64_encode(str_repeat('a', 32)).':'])
            ->assertStatus(400)
            ->assertProblemType('https://apiguide.dev/errors/digest-invalid-values')
            ->assertJsonPath('invalid_digests.0', ['algorithm' => 'sha-512', 'header' => 'Repr-Digest', 'reason' => 'digest value is not 64 bytes long']);
    });

    it('refuses RFC 9530 Appendix B.5 own value as a field that does not parse, with a plain 400', function (): void {
        // The stray "=" is not valid base64, and RFC 9651 says that fails the whole field, which
        // the draft says its problem types do not cover.
        putWith($this, ['Repr-Digest' => 'sha-256=:RK/0qy18MlBSVnWgjwz6lZEWjP/lF5HF9bvEF8FabDg==:'])
            ->assertStatus(400)
            ->assertProblemType('about:blank', 400);
    });

    it('refuses a digest value that is not a Byte Sequence as invalid', function (): void {
        putWith($this, ['Repr-Digest' => 'sha-256="not bytes"'])
            ->assertStatus(400)
            ->assertJsonPath('invalid_digests.0.reason', 'digest value is not a Byte Sequence');
    });

    it('refuses only unsupported algorithms, advertising the supported ones', function (): void {
        putWith($this, ['Content-Digest' => 'md5=:XrY7u+Ae7tCTyyK7j1rNww==:'])
            ->assertStatus(400)
            ->assertProblemType('https://apiguide.dev/errors/digest-unsupported-algorithms')
            ->assertJsonPath('unsupported_algorithms', [['algorithm' => 'md5', 'header' => 'Content-Digest']])
            ->assertHeader('Want-Content-Digest', 'sha-256=10, sha-512=10');
    });

    it('refuses a field that is not a Dictionary with a plain 400', function (): void {
        putWith($this, ['Content-Digest' => 'not a dictionary'])->assertStatus(400)->assertProblemType('about:blank');
    });

    it('passes a request with no digest, unless one is required', function (): void {
        putWith($this, [])->assertNoContent();
        putWith($this, [], 'api/strict/123')->assertStatus(400)->assertHeader('Want-Content-Digest', 'sha-256=10, sha-512=10');
    });
});

describe('adding to responses', function (): void {
    it('adds the digests the request wants', function (): void {
        $this->get('api/items/123', ['Want-Repr-Digest' => 'sha-512=3, sha-256=10, unixsum=0', 'Want-Content-Digest' => 'sha-256=1'])
            ->assertHeader('Repr-Digest', SHA256)
            ->assertHeader('Content-Digest', SHA256)
            ->assertReprDigest()
            ->assertContentDigest();
    });

    it('adds nothing unasked', function (): void {
        $this->get('api/items/123')->assertHeaderMissing('Repr-Digest')->assertHeaderMissing('Content-Digest');
    });

    it('ignores wants it cannot meet, rather than failing (section 4)', function (): void {
        $this->get('api/items/123', ['Want-Repr-Digest' => 'md5=10'])->assertOk()->assertHeaderMissing('Repr-Digest');
    });

    it('always adds Repr-Digest when told to', function (): void {
        $this->get('api/always/123')->assertHeader('Repr-Digest', SHA256);
    });

    it('gives HEAD no digest rather than a digest of the content Laravel has already removed', function (): void {
        $this->call('HEAD', 'api/items/123', [], [], [], ['HTTP_WANT_REPR_DIGEST' => 'sha-256=1', 'HTTP_WANT_CONTENT_DIGEST' => 'sha-256=1'])
            ->assertOk()
            ->assertHeaderMissing('Repr-Digest')
            ->assertHeaderMissing('Content-Digest');
    });
});
