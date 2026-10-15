<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use JustSteveKing\Toolbox\MergePatch\MergePatch;

beforeEach(function (): void {
    $this->post = ['title' => 'Goodbye!', 'summary' => 'Old summary', 'tags' => ['example', 'sample']];

    Route::patch('api/posts/1', function (MergePatch $patch) {
        $document = $patch->applyTo($this->post);

        // The merged document is what gets validated: the patch itself means "remove" by null.
        Validator::make(is_array($document) ? $document : [], [
            'title' => ['required', 'string'],
            'summary' => ['nullable', 'string'],
            'tags' => ['array'],
        ])->validate();

        return ['document' => $document, 'changes' => $patch->changesTo($this->post)];
    })->middleware('merge-patch');
});

function mergePatch(PHPUnit\Framework\TestCase $test, string $body, string $type = 'application/merge-patch+json'): Illuminate\Testing\TestResponse
{
    return $test->call('PATCH', 'api/posts/1', [], [], [], ['CONTENT_TYPE' => $type, 'HTTP_ACCEPT' => 'application/json'], $body);
}

it('applies the patch and reports the changes', function (): void {
    mergePatch($this, '{"title":"Hello!","summary":null,"tags":["example"]}')
        ->assertOk()
        ->assertHeader('Accept-Patch', 'application/merge-patch+json')
        ->assertExactJson([
            'document' => ['title' => 'Hello!', 'tags' => ['example']],
            'changes' => ['title' => 'Hello!', 'summary' => null, 'tags' => ['example']],
        ]);
});

it('validates the merged document, so removing a required field fails', function (): void {
    mergePatch($this, '{"title":null}')
        ->assertStatus(422)
        ->assertProblemType('https://apiguide.dev/errors/validation-failed');
});

it('answers 415 with Accept-Patch to any other format (RFC 5789 section 2.2)', function (string $type): void {
    mergePatch($this, '[{"op":"replace","path":"/title","value":"x"}]', $type)
        ->assertStatus(415)
        ->assertHeader('Accept-Patch', 'application/merge-patch+json')
        ->assertProblemType('https://apiguide.dev/errors/unsupported-media-type');
})->with(['application/json-patch+json', 'application/json']);

it('accepts the media type with parameters', function (): void {
    mergePatch($this, '{"title":"Hello!"}', 'application/merge-patch+json; charset=utf-8')->assertOk();
});

it('answers a patch that is not JSON with the apiguide.dev malformed-request-body type', function (): void {
    mergePatch($this, '{"title":')
        ->assertStatus(400)
        ->assertProblemType('https://apiguide.dev/errors/malformed-request-body');
});
