<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use JustSteveKing\Toolbox\Tests\Fixtures\Models\Document;

beforeEach(function (): void {
    Schema::create('documents', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    $this->document = Document::query()->create(['title' => 'Draft']);
    $this->document->forceFill(['updated_at' => '2026-10-15 09:00:00'])->saveQuietly();

    Route::middleware([SubstituteBindings::class, 'conditional'])->group(function (): void {
        Route::get('api/documents/{document}', fn (Document $document) => ['title' => $document->title]);
        Route::patch('api/documents/{document}', function (Request $request, Document $document) {
            $document->update(['title' => $request->string('title')->toString()]);

            return ['title' => $document->title];
        });
        Route::put('api/drafts/{slug}', fn (string $slug) => response(['slug' => $slug], 201));
        Route::get('api/content', fn () => ['hello' => 'world']);
    });

    Route::get('api/content/weak', fn () => ['hello' => 'world'])->middleware(['conditional', 'validators:weak']);
});

function currentTag(): string
{
    return Document::query()->firstOrFail()->entityTag()->toString();
}

describe('validators', function (): void {
    it('sends the resource ETag and Last-Modified on GET', function (): void {
        $this->get('api/documents/1')
            ->assertOk()
            ->assertETag(currentTag(), weak: false)
            ->assertHeader('Last-Modified', 'Thu, 15 Oct 2026 09:00:00 GMT');
    });

    it('hashes the content when there is no resource to ask', function (): void {
        $this->get('api/content')->assertETag('"'.hash('xxh128', '{"hello":"world"}').'"');
    });

    it('marks a content hash weak when asked to', function (): void {
        $this->get('api/content/weak')->assertETag(weak: true);
    });

    it('sends no validators on unsafe methods (RFC 9110 section 9.3.4)', function (): void {
        $this->patch('api/documents/1', ['title' => 'Final'], ['If-Match' => currentTag()])
            ->assertOk()
            ->assertHeaderMissing('ETag')
            ->assertHeaderMissing('Last-Modified');
    });
});

describe('cache validation on GET', function (): void {
    it('answers 304 when If-None-Match matches, keeping the ETag and dropping the body', function (): void {
        $this->get('api/documents/1', ['If-None-Match' => currentTag()])
            ->assertNotModified()
            ->assertETag(currentTag())
            ->assertHeaderMissing('Last-Modified')
            ->assertHeaderMissing('Content-Type');
    });

    it('matches If-None-Match weakly', function (): void {
        $this->get('api/documents/1', ['If-None-Match' => 'W/'.currentTag()])->assertNotModified();
    });

    it('answers 304 when not modified since', function (): void {
        $this->get('api/content', ['If-Modified-Since' => 'Thu, 15 Oct 2026 09:00:00 GMT'])->assertOk();

        $this->get('api/documents/1', ['If-Modified-Since' => 'Thu, 15 Oct 2026 09:00:00 GMT'])->assertNotModified();
    });

    it('validates against a Last-Modified the response already carries', function (): void {
        Route::get('api/dated', fn () => response('dated', 200, ['Last-Modified' => 'Thu, 15 Oct 2026 09:00:00 GMT']))
            ->middleware('preconditions');

        $this->get('api/dated', ['If-Modified-Since' => 'Thu, 15 Oct 2026 09:00:00 GMT'])->assertNotModified();
        $this->get('api/dated', ['If-Modified-Since' => 'Wed, 14 Oct 2026 09:00:00 GMT'])->assertOk();
    });

    it('serves the representation when it has changed', function (): void {
        $this->get('api/documents/1', ['If-None-Match' => '"stale"'])->assertOk()->assertJson(['title' => 'Draft']);
    });

    it('ignores preconditions when the response would not be 2xx (section 13.2.1)', function (): void {
        $this->getJson('api/documents/999', ['If-Match' => '"anything"'])->assertNotFound();
    });
});

describe('optimistic concurrency', function (): void {
    it('applies a write whose If-Match is current', function (): void {
        $this->patch('api/documents/1', ['title' => 'Final'], ['If-Match' => currentTag()])->assertOk();

        expect(Document::query()->firstOrFail()->title)->toBe('Final');
    });

    it('refuses a stale write with 412, the apiguide.dev type and the current ETag', function (): void {
        $current = currentTag();

        $this->patchJson('api/documents/1', ['title' => 'Final'], ['If-Match' => '"stale"'])
            ->assertPreconditionFailed()
            ->assertProblemType('https://apiguide.dev/errors/precondition-failed', 412)
            ->assertETag($current);

        expect(Document::query()->firstOrFail()->title)->toBe('Draft');
    });

    it('never matches a weak tag for If-Match (section 13.1.1)', function (): void {
        $this->patch('api/documents/1', ['title' => 'Final'], ['If-Match' => 'W/'.currentTag()])->assertPreconditionFailed();
    });

    it('refuses a write when modified since the client read it', function (): void {
        $this->patch('api/documents/1', ['title' => 'Final'], ['If-Unmodified-Since' => 'Wed, 14 Oct 2026 09:00:00 GMT'])
            ->assertPreconditionFailed();
    });

    it('lets "If-None-Match: *" create, and "If-Match: *" refuse, where nothing exists yet', function (): void {
        $this->put('api/drafts/new', [], ['If-None-Match' => '*'])->assertCreated();
        $this->put('api/drafts/new', [], ['If-Match' => '*'])->assertPreconditionFailed();
    });
});

describe('preconditions.required', function (): void {
    beforeEach(function (): void {
        Route::patch('api/locked/{document}', fn (Document $document) => ['title' => $document->title])
            // Listed out of order on purpose: the priority list puts bindings first, then 428,
            // then 412.
            ->middleware(['conditional', 'preconditions.required', SubstituteBindings::class]);
    });

    it('answers 428 with the apiguide.dev type to a write with no precondition', function (): void {
        $this->patchJson('api/locked/1')
            ->assertPreconditionRequired()
            ->assertProblemType('https://apiguide.dev/errors/precondition-required', 428);
    });

    it('lets a conditional write through, and still evaluates it', function (): void {
        $this->patch('api/locked/1', [], ['If-Match' => currentTag()])->assertOk();
        $this->patch('api/locked/1', [], ['If-Match' => '"stale"'])->assertPreconditionFailed();
    });

    it('does not count If-Modified-Since, which is ignored on writes', function (): void {
        $this->patch('api/locked/1', [], ['If-Modified-Since' => 'Thu, 15 Oct 2026 09:00:00 GMT'])->assertStatus(428);
    });

    it('only applies to the methods it is given', function (): void {
        Route::post('api/locked', fn () => 'created')->middleware('preconditions.required:PUT');

        $this->post('api/locked')->assertOk();
    });
});
